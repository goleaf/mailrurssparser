<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Article;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ArticlePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_article');
    }

    public function view(AuthUser $authUser, Article $article): bool
    {
        return $authUser->can('view_article');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_article');
    }

    public function update(AuthUser $authUser, Article $article): bool
    {
        return $authUser->can('update_article');
    }

    public function delete(AuthUser $authUser, Article $article): bool
    {
        return $authUser->can('delete_article');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_article');
    }

    public function forceDelete(AuthUser $authUser, Article $article): bool
    {
        return $authUser->can('force_delete_article');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_article');
    }

    public function restore(AuthUser $authUser, Article $article): bool
    {
        return $authUser->can('restore_article');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_article');
    }
}
