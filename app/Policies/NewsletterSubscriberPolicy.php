<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NewsletterSubscriber;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class NewsletterSubscriberPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_newsletter_subscriber');
    }

    public function view(AuthUser $authUser, NewsletterSubscriber $newsletterSubscriber): bool
    {
        return $authUser->can('view_newsletter_subscriber');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_newsletter_subscriber');
    }

    public function update(AuthUser $authUser, NewsletterSubscriber $newsletterSubscriber): bool
    {
        return $authUser->can('update_newsletter_subscriber');
    }

    public function delete(AuthUser $authUser, NewsletterSubscriber $newsletterSubscriber): bool
    {
        return $authUser->can('delete_newsletter_subscriber');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_newsletter_subscriber');
    }

    public function forceDelete(AuthUser $authUser, NewsletterSubscriber $newsletterSubscriber): bool
    {
        return $authUser->can('force_delete_newsletter_subscriber');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_newsletter_subscriber');
    }

    public function restore(AuthUser $authUser, NewsletterSubscriber $newsletterSubscriber): bool
    {
        return $authUser->can('restore_newsletter_subscriber');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_newsletter_subscriber');
    }
}
