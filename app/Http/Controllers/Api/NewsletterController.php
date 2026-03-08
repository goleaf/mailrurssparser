<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\NewsletterSubscribeRequest;
use App\Models\NewsletterSubscriber;
use App\Services\MetricTracker;
use App\Services\RequestLocationService;
use App\Services\SendNewsletterConfirmationMail;
use App\Services\TrackedMetric;
use Illuminate\Http\JsonResponse;

class NewsletterController extends Controller
{
    public function __construct(
        private readonly RequestLocationService $requestLocation,
    ) {}

    public function subscribe(NewsletterSubscribeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $location = $this->requestLocation->resolve($request);

        $existing = NewsletterSubscriber::query()->where('email', $validated['email'])->first();

        if ($existing !== null && $existing->confirmed && $existing->unsubscribed_at === null) {
            return response()->json(['already_subscribed' => true]);
        }

        if ($existing !== null && ! $existing->confirmed) {
            SendNewsletterConfirmationMail::dispatch($existing);

            return response()->json(['resent' => true]);
        }

        $subscriber = NewsletterSubscriber::query()->updateOrCreate(
            ['email' => $validated['email']],
            [
                'name' => $validated['name'] ?? null,
                'category_ids' => $validated['category_ids'] ?? null,
                'confirmed' => false,
                'confirmed_at' => null,
                'unsubscribed_at' => null,
                'ip_address' => $request->ip(),
                'country_code' => $location['country_code'] ?? null,
                'timezone' => $location['timezone'] ?? null,
                'locale' => $location['locale'] ?? null,
            ],
        );

        SendNewsletterConfirmationMail::dispatch($subscriber);
        app(MetricTracker::class)->record(TrackedMetric::NewsletterSubscription);

        return response()->json([
            'success' => true,
            'message' => 'Проверьте почту для подтверждения',
        ]);
    }

    public function confirm(string $token): JsonResponse
    {
        $subscriber = NewsletterSubscriber::query()->where('token', $token)->firstOrFail();

        if ($subscriber->confirmed) {
            return response()->json(['already_confirmed' => true]);
        }

        $subscriber->update([
            'confirmed' => true,
            'confirmed_at' => now(),
            'unsubscribed_at' => null,
        ]);

        app(MetricTracker::class)->record(TrackedMetric::NewsletterConfirmation);

        return response()->json([
            'success' => true,
            'message' => 'Подписка подтверждена',
        ]);
    }

    public function unsubscribe(string $token): JsonResponse
    {
        $subscriber = NewsletterSubscriber::query()->where('token', $token)->firstOrFail();
        $shouldRecordMetric = $subscriber->unsubscribed_at === null;

        $subscriber->update(['unsubscribed_at' => now()]);

        if ($shouldRecordMetric) {
            app(MetricTracker::class)->record(TrackedMetric::NewsletterUnsubscription);
        }

        return response()->json(['success' => true]);
    }
}
