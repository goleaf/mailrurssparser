<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RssFeed;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RssFeedPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_rss_feed');
    }

    public function view(AuthUser $authUser, RssFeed $rssFeed): bool
    {
        return $authUser->can('view_rss_feed');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_rss_feed');
    }

    public function update(AuthUser $authUser, RssFeed $rssFeed): bool
    {
        return $authUser->can('update_rss_feed');
    }

    public function delete(AuthUser $authUser, RssFeed $rssFeed): bool
    {
        return $authUser->can('delete_rss_feed');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_rss_feed');
    }

    public function forceDelete(AuthUser $authUser, RssFeed $rssFeed): bool
    {
        return $authUser->can('force_delete_rss_feed');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_rss_feed');
    }

    public function restore(AuthUser $authUser, RssFeed $rssFeed): bool
    {
        return $authUser->can('restore_rss_feed');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_rss_feed');
    }
}
