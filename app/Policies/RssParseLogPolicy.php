<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RssParseLog;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RssParseLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_rss_parse_log');
    }

    public function view(AuthUser $authUser, RssParseLog $rssParseLog): bool
    {
        return $authUser->can('view_rss_parse_log');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_rss_parse_log');
    }

    public function update(AuthUser $authUser, RssParseLog $rssParseLog): bool
    {
        return $authUser->can('update_rss_parse_log');
    }

    public function delete(AuthUser $authUser, RssParseLog $rssParseLog): bool
    {
        return $authUser->can('delete_rss_parse_log');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_rss_parse_log');
    }

    public function forceDelete(AuthUser $authUser, RssParseLog $rssParseLog): bool
    {
        return $authUser->can('force_delete_rss_parse_log');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_rss_parse_log');
    }

    public function restore(AuthUser $authUser, RssParseLog $rssParseLog): bool
    {
        return $authUser->can('restore_rss_parse_log');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_rss_parse_log');
    }
}
