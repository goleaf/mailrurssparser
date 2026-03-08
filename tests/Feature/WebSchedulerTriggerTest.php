<?php

use App\Http\Middleware\TriggerSchedulerFromWebRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

function makeSchedulerPulseRequest(): Request
{
    $request = Request::create('/scheduler/pulse', 'GET');
    $request->headers->set('X-Run-Web-Scheduler', '1');
    $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

    return $request;
}

beforeEach(function () {
    File::delete(TriggerSchedulerFromWebRequests::stateFilePath());
});

afterEach(function () {
    Date::setTestNow();
    File::delete(TriggerSchedulerFromWebRequests::stateFilePath());
});

it('triggers the scheduler from the heartbeat route', function () {
    Artisan::spy();

    $middleware = new TriggerSchedulerFromWebRequests;

    $middleware->terminate(makeSchedulerPulseRequest(), new Response('', 204));

    Artisan::shouldHaveReceived('call')
        ->once()
        ->with('schedule:run');
});

it('throttles repeated scheduler triggers inside one minute', function () {
    Date::setTestNow(now()->startOfMinute());

    Artisan::spy();
    $middleware = new TriggerSchedulerFromWebRequests;

    $middleware->terminate(makeSchedulerPulseRequest(), new Response('', 204));
    $middleware->terminate(makeSchedulerPulseRequest(), new Response('', 204));

    Artisan::shouldHaveReceived('call')
        ->once()
        ->with('schedule:run');
});

it('allows another scheduler run after a minute passes', function () {
    $startedAt = now()->startOfMinute();

    Date::setTestNow($startedAt);

    Artisan::spy();
    $middleware = new TriggerSchedulerFromWebRequests;

    $middleware->terminate(makeSchedulerPulseRequest(), new Response('', 204));

    Date::setTestNow($startedAt->addSeconds(61));

    $middleware->terminate(makeSchedulerPulseRequest(), new Response('', 204));

    Artisan::shouldHaveReceived('call')
        ->twice()
        ->with('schedule:run');
});
