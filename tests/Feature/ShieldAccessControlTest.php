<?php

use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function shieldGuardName(): string
{
    return Filament::getPanel('admin')?->getAuthGuard() ?? config('auth.defaults.guard', 'web');
}

function standardPermissionPrefixes(): array
{
    return [
        'view',
        'view_any',
        'create',
        'update',
        'delete',
        'delete_any',
        'force_delete',
        'force_delete_any',
        'restore',
        'restore_any',
    ];
}

function shieldResources(): array
{
    return [
        'article',
        'article_view',
        'bookmark',
        'category',
        'metric',
        'newsletter_subscriber',
        'role',
        'rss_feed',
        'rss_parse_log',
        'sub_category',
        'tag',
    ];
}

function seededEditorPermissions(): array
{
    return [
        'view_any_article',
        'view_article',
        'create_article',
        'update_article',
        'view_any_tag',
        'view_tag',
        'create_tag',
        'update_tag',
        'delete_tag',
        'delete_any_tag',
        'view_any_category',
        'view_category',
        'create_category',
        'update_category',
        'view_any_sub_category',
        'view_sub_category',
        'create_sub_category',
        'update_sub_category',
        'view_any_rss_feed',
        'view_rss_feed',
        'view_any_rss_parse_log',
        'view_rss_parse_log',
        'view_any_metric',
        'view_metric',
        'view_any_newsletter_subscriber',
        'view_newsletter_subscriber',
        'view_any_article_view',
        'view_article_view',
    ];
}

function seededViewerPermissions(): array
{
    return [
        'view_any_article',
        'view_article',
        'view_any_tag',
        'view_tag',
        'view_any_category',
        'view_category',
    ];
}

function sortedPermissionNames(iterable $permissions): array
{
    return collect($permissions)->sort()->values()->all();
}

it('uses the expected Shield configuration for resource policies', function () {
    expect(config('filament-shield.auth_provider_model'))->toBe(User::class)
        ->and(config('filament-shield.super_admin.name'))->toBe('super_admin')
        ->and(config('filament-shield.panel_user.name'))->toBe('admin')
        ->and(config('filament-shield.permissions.separator'))->toBe('_')
        ->and(config('filament-shield.permissions.case'))->toBe('lower_snake')
        ->and(config('filament-shield.shield_resource.tabs.resources'))->toBeTrue()
        ->and(config('filament-shield.shield_resource.tabs.pages'))->toBeFalse()
        ->and(config('filament-shield.shield_resource.tabs.widgets'))->toBeFalse()
        ->and(config('filament-shield.shield_resource.tabs.custom_permissions'))->toBeFalse();
});

it('seeds the expected Shield roles and permissions', function () {
    $guardName = shieldGuardName();

    foreach (shieldResources() as $resource) {
        foreach (standardPermissionPrefixes() as $prefix) {
            Permission::findOrCreate("{$prefix}_{$resource}", $guardName);
        }
    }

    $this->seed(ShieldSeeder::class);

    $allPermissions = Permission::query()
        ->where('guard_name', $guardName)
        ->pluck('name')
        ->all();

    $admin = Role::findByName('admin', $guardName);
    $superAdmin = Role::findByName('super_admin', $guardName);
    $editor = Role::findByName('editor', $guardName);
    $viewer = Role::findByName('viewer', $guardName);

    expect(sortedPermissionNames($admin->permissions->pluck('name')->all()))->toBe([])
        ->and(sortedPermissionNames($superAdmin->permissions->pluck('name')->all()))
        ->toBe(sortedPermissionNames($allPermissions))
        ->and(sortedPermissionNames($editor->permissions->pluck('name')->all()))
        ->toBe(sortedPermissionNames(seededEditorPermissions()))
        ->and(sortedPermissionNames($viewer->permissions->pluck('name')->all()))
        ->toBe(sortedPermissionNames(seededViewerPermissions()));
});
