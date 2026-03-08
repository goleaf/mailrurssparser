<?php

namespace App\Services;

use Devrabiul\LaravelGeoGenius\Services\GeoLocationService;
use Devrabiul\LaravelGeoGenius\Services\LanguageService;
use Illuminate\Http\Request;
use Skywalker\Location\Facades\Location;
use Skywalker\Location\Position;
use Throwable;

class RequestLocationService
{
    public function __construct(
        private readonly GeoLocationService $geoLocation,
        private readonly LanguageService $language,
    ) {}

    /**
     * @return array{country_code?: string, country_name?: string, timezone?: string, locale?: string}
     */
    public function resolve(Request $request): array
    {
        $ipAddress = $request->ip();
        $headerLocation = $this->fallbackFromHeaders($request);
        $locale = $this->resolveLocale($request);

        if (! is_string($ipAddress) || $ipAddress === '') {
            return $this->withLocale($headerLocation, $locale);
        }

        try {
            $position = Location::get($ipAddress);
        } catch (Throwable) {
            return $this->withLocale(
                $this->mergeGeoContext($request, $headerLocation),
                $locale,
            );
        }

        if (! $position instanceof Position || $position->isEmpty()) {
            return $this->withLocale(
                $this->mergeGeoContext($request, $headerLocation),
                $locale,
            );
        }

        $countryCode = $this->normalizeCountryCode($position->countryCode);

        $context = $headerLocation;

        if ($countryCode !== null) {
            $context['country_code'] = $countryCode;
        }

        return $this->withLocale(
            $this->mergeGeoContext($request, $context),
            $locale,
        );
    }

    /**
     * @param  array<string, string>  $context
     * @return array{country_code?: string, country_name?: string, timezone?: string, locale?: string}
     */
    private function withLocale(array $context, ?string $locale): array
    {
        if ($locale !== null) {
            $context['locale'] = $locale;
        }

        return $context;
    }

    /**
     * @return array{country_code?: string, timezone?: string, locale?: string}
     */
    private function fallbackFromHeaders(Request $request): array
    {
        $context = [];
        $countryCode = $this->normalizeCountryCode(
            $request->header('cf-ipcountry') ?? $request->header('x-country-code'),
        );
        $timezone = $this->normalizeTimezone(
            $request->header('x-timezone') ?? $request->header('cf-timezone'),
        );
        $locale = $this->normalizeLocale(
            $request->header('x-locale'),
        );

        if ($countryCode !== null) {
            $context['country_code'] = $countryCode;
        }

        if ($timezone !== null) {
            $context['timezone'] = $timezone;
        }

        if ($locale !== null) {
            $context['locale'] = $locale;
        }

        return $context;
    }

    /**
     * @param  array<string, string>  $context
     * @return array<string, string>
     */
    private function mergeGeoContext(Request $request, array $context): array
    {
        if (! $this->shouldUseGeoGenius($request, $context)) {
            return $context;
        }

        try {
            $geoData = $this->geoLocation->locateVisitor();
        } catch (Throwable) {
            return $context;
        }

        if (! is_array($geoData) || (($geoData['success'] ?? true) === false)) {
            return $context;
        }

        $countryCode = $this->normalizeCountryCode($geoData['countryCode'] ?? $geoData['country_code'] ?? null);
        $countryName = $this->normalizeCountryName($geoData['country'] ?? null);
        $timezone = $this->normalizeTimezone($geoData['timezone'] ?? null);

        if (! array_key_exists('country_code', $context) && $countryCode !== null) {
            $context['country_code'] = $countryCode;
        }

        if ($countryName !== null) {
            $context['country_name'] = $countryName;
        }

        if (! array_key_exists('timezone', $context) && $timezone !== null) {
            $context['timezone'] = $timezone;
        }

        return $context;
    }

    /**
     * @param  array<string, string>  $context
     */
    private function shouldUseGeoGenius(Request $request, array $context): bool
    {
        if (array_key_exists('country_code', $context) && array_key_exists('timezone', $context)) {
            return false;
        }

        $ipAddress = $request->ip();

        if (! is_string($ipAddress) || $ipAddress === '') {
            return false;
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            return true;
        }

        foreach (['cf-connecting-ip', 'x-forwarded-for', 'x-real-ip'] as $header) {
            $forwardedFor = $request->header($header);

            if (! is_string($forwardedFor) || $forwardedFor === '') {
                continue;
            }

            $forwardedIp = trim(explode(',', $forwardedFor)[0]);

            if (filter_var($forwardedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                return true;
            }
        }

        return false;
    }

    private function resolveLocale(Request $request): ?string
    {
        $locale = $this->normalizeLocale(
            $request->header('x-locale')
                ?? $this->language->detect()
                ?? $request->getPreferredLanguage(),
        );

        return $locale !== '' ? $locale : null;
    }

    private function normalizeCountryCode(mixed $countryCode): ?string
    {
        if (! is_string($countryCode)) {
            return null;
        }

        $countryCode = strtoupper(trim($countryCode));

        if (strlen($countryCode) !== 2) {
            return null;
        }

        return $countryCode;
    }

    private function normalizeCountryName(mixed $countryName): ?string
    {
        if (! is_string($countryName)) {
            return null;
        }

        $countryName = trim($countryName);

        return $countryName !== '' ? $countryName : null;
    }

    private function normalizeTimezone(mixed $timezone): ?string
    {
        if (! is_string($timezone)) {
            return null;
        }

        $timezone = trim($timezone);

        if ($timezone === '' || ! in_array($timezone, timezone_identifiers_list(), true)) {
            return null;
        }

        return $timezone;
    }

    private function normalizeLocale(mixed $locale): ?string
    {
        if (! is_string($locale)) {
            return null;
        }

        $locale = strtolower(str_replace('_', '-', trim($locale)));

        if ($locale === '') {
            return null;
        }

        $primaryLocale = explode('-', $locale)[0];

        if ($primaryLocale === '' || ! preg_match('/^[a-z]{2,3}$/', $primaryLocale)) {
            return null;
        }

        return $primaryLocale;
    }
}
