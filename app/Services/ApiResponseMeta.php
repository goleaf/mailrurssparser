<?php

namespace App\Services;

use Illuminate\Http\Request;

final class ApiResponseMeta
{
    public const REQUEST_ID_ATTRIBUTE = 'api_request_id';

    public const VERSION_ATTRIBUTE = 'api_version';

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function fromRequest(Request $request, array $meta = []): array
    {
        return array_merge($meta, array_filter([
            'request_id' => self::requestIdFor($request),
            'api_version' => self::versionFor($request),
            'generated_at' => now()->toIso8601String(),
        ], fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    public static function requestIdFor(Request $request): ?string
    {
        $requestId = $request->attributes->get(self::REQUEST_ID_ATTRIBUTE);

        return is_string($requestId) && $requestId !== '' ? $requestId : null;
    }

    public static function versionFor(Request $request): string
    {
        $version = $request->attributes->get(self::VERSION_ATTRIBUTE);

        if (is_string($version) && $version !== '') {
            return $version;
        }

        $routeName = (string) $request->route()?->getName();

        if (preg_match('/^api\.(v\d+)\./', $routeName, $matches) === 1) {
            return $matches[1];
        }

        $segments = $request->segments();

        if (($segments[0] ?? null) === 'api' && is_string($segments[1] ?? null) && $segments[1] !== '') {
            return $segments[1];
        }

        return 'v1';
    }
}
