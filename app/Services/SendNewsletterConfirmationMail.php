<?php

namespace App\Services;

use App\Mail\ConfirmSubscriptionMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendNewsletterConfirmationMail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public NewsletterSubscriber $subscriber,
    ) {
        $this->onConnection('deferred');
    }

    public function handle(): void
    {
        Mail::to($this->subscriber->email)->send(new ConfirmSubscriptionMail($this->subscriber));
    }
}
