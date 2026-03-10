<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Bookmark;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class BookmarkPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_bookmark');
    }

    public function view(AuthUser $authUser, Bookmark $bookmark): bool
    {
        return $authUser->can('view_bookmark');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_bookmark');
    }

    public function update(AuthUser $authUser, Bookmark $bookmark): bool
    {
        return $authUser->can('update_bookmark');
    }

    public function delete(AuthUser $authUser, Bookmark $bookmark): bool
    {
        return $authUser->can('delete_bookmark');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_bookmark');
    }

    public function forceDelete(AuthUser $authUser, Bookmark $bookmark): bool
    {
        return $authUser->can('force_delete_bookmark');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_bookmark');
    }

    public function restore(AuthUser $authUser, Bookmark $bookmark): bool
    {
        return $authUser->can('restore_bookmark');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_bookmark');
    }
}
