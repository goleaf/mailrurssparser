<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Metric;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MetricPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_metric');
    }

    public function view(AuthUser $authUser, Metric $metric): bool
    {
        return $authUser->can('view_metric');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_metric');
    }

    public function update(AuthUser $authUser, Metric $metric): bool
    {
        return $authUser->can('update_metric');
    }

    public function delete(AuthUser $authUser, Metric $metric): bool
    {
        return $authUser->can('delete_metric');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_metric');
    }

    public function forceDelete(AuthUser $authUser, Metric $metric): bool
    {
        return $authUser->can('force_delete_metric');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_metric');
    }

    public function restore(AuthUser $authUser, Metric $metric): bool
    {
        return $authUser->can('restore_metric');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_metric');
    }
}
