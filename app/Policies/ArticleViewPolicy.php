<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ArticleView;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ArticleViewPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_article_view');
    }

    public function view(AuthUser $authUser, ArticleView $articleView): bool
    {
        return $authUser->can('view_article_view');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_article_view');
    }

    public function update(AuthUser $authUser, ArticleView $articleView): bool
    {
        return $authUser->can('update_article_view');
    }

    public function delete(AuthUser $authUser, ArticleView $articleView): bool
    {
        return $authUser->can('delete_article_view');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_article_view');
    }

    public function forceDelete(AuthUser $authUser, ArticleView $articleView): bool
    {
        return $authUser->can('force_delete_article_view');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_article_view');
    }

    public function restore(AuthUser $authUser, ArticleView $articleView): bool
    {
        return $authUser->can('restore_article_view');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_article_view');
    }
}
