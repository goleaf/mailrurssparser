<?php

namespace App\Http\Middleware;

use App\Services\ApiResponseMeta;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ApplyApiRequestContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $version = ApiResponseMeta::versionFor($request);

        $request->attributes->set(ApiResponseMeta::REQUEST_ID_ATTRIBUTE, $requestId);
        $request->attributes->set(ApiResponseMeta::VERSION_ATTRIBUTE, $version);

        Context::add('request_id', $requestId);
        Context::add('api_version', $version);
        Context::add('request_path', $request->path());
        Context::add('route_name', (string) $request->route()?->getName());

        $response = $next($request);

        $this->appendJsonMeta($request, $response);

        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('X-Api-Version', $version);

        return $response;
    }

    protected function appendJsonMeta(Request $request, Response $response): void
    {
        if (! $response instanceof JsonResponse || $response->getContent() === '') {
            return;
        }

        $payload = $response->getData(true);

        if (! is_array($payload) || array_is_list($payload)) {
            return;
        }

        $existingMeta = $payload['meta'] ?? [];

        if (! is_array($existingMeta)) {
            $existingMeta = [];
        }

        $payload['meta'] = ApiResponseMeta::fromRequest($request, $existingMeta);

        $response->setData($payload);
    }
}
