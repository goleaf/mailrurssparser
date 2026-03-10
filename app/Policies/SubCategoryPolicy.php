<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SubCategory;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SubCategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_sub_category');
    }

    public function view(AuthUser $authUser, SubCategory $subCategory): bool
    {
        return $authUser->can('view_sub_category');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_sub_category');
    }

    public function update(AuthUser $authUser, SubCategory $subCategory): bool
    {
        return $authUser->can('update_sub_category');
    }

    public function delete(AuthUser $authUser, SubCategory $subCategory): bool
    {
        return $authUser->can('delete_sub_category');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_sub_category');
    }

    public function forceDelete(AuthUser $authUser, SubCategory $subCategory): bool
    {
        return $authUser->can('force_delete_sub_category');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_sub_category');
    }

    public function restore(AuthUser $authUser, SubCategory $subCategory): bool
    {
        return $authUser->can('restore_sub_category');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_sub_category');
    }
}
