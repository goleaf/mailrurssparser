<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\NewsletterSubscribeRequest;
use App\Mail\ConfirmSubscriptionMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class NewsletterController extends Controller
{
    public function subscribe(NewsletterSubscribeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $existing = NewsletterSubscriber::query()->where('email', $validated['email'])->first();

        if ($existing !== null && $existing->confirmed && $existing->unsubscribed_at === null) {
            return response()->json(['already_subscribed' => true]);
        }

        if ($existing !== null && ! $existing->confirmed) {
            Mail::to($existing->email)->send(new ConfirmSubscriptionMail($existing));

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
            ],
        );

        Mail::to($subscriber->email)->send(new ConfirmSubscriptionMail($subscriber));

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

        return response()->json([
            'success' => true,
            'message' => 'Подписка подтверждена',
        ]);
    }

    public function unsubscribe(string $token): JsonResponse
    {
        $subscriber = NewsletterSubscriber::query()->where('token', $token)->firstOrFail();

        $subscriber->update(['unsubscribed_at' => now()]);

        return response()->json(['success' => true]);
    }
}
