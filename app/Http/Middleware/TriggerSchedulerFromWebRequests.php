<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class TriggerSchedulerFromWebRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $this->shouldTriggerScheduler($request, $response)) {
            return;
        }

        if (! $this->reserveSchedulerWindow()) {
            return;
        }

        Artisan::call('schedule:run');
    }

    public static function stateFilePath(): string
    {
        return storage_path('framework/auto-scheduler/last-run-at');
    }

    protected function shouldTriggerScheduler(Request $request, Response $response): bool
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return false;
        }

        if (
            app()->runningUnitTests()
            && ! filter_var($request->headers->get('X-Run-Web-Scheduler'), FILTER_VALIDATE_BOOL)
        ) {
            return false;
        }

        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return false;
        }

        if ($response->isServerError()) {
            return false;
        }

        if ($request->isXmlHttpRequest() && ! $request->routeIs('scheduler.pulse')) {
            return false;
        }

        return true;
    }

    protected function reserveSchedulerWindow(): bool
    {
        $stateFilePath = self::stateFilePath();

        File::ensureDirectoryExists(dirname($stateFilePath));

        $handle = fopen($stateFilePath, 'c+');

        if ($handle === false) {
            return false;
        }

        try {
            if (! flock($handle, LOCK_EX | LOCK_NB)) {
                return false;
            }

            rewind($handle);

            $lastRunAt = trim((string) stream_get_contents($handle));
            $currentTimestamp = now()->timestamp;

            if ($lastRunAt !== '' && ctype_digit($lastRunAt) && ($currentTimestamp - (int) $lastRunAt) < 60) {
                return false;
            }

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, (string) $currentTimestamp);
            fflush($handle);

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
