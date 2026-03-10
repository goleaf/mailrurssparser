<?php

namespace Database\Seeders;

use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        $guardName = Filament::getPanel('admin')?->getAuthGuard() ?? config('auth.defaults.guard', 'web');

        Role::findOrCreate((string) config('filament-shield.panel_user.name', 'admin'), $guardName)
            ->syncPermissions([]);

        Role::findOrCreate('super_admin', $guardName)
            ->syncPermissions(
                Permission::query()
                    ->where('guard_name', $guardName)
                    ->pluck('name')
                    ->all(),
            );

        Role::findOrCreate('editor', $guardName)
            ->syncPermissions($this->editorPermissions());

        Role::findOrCreate('viewer', $guardName)
            ->syncPermissions($this->viewerPermissions());
    }

    protected function editorPermissions(): array
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

    protected function viewerPermissions(): array
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
}
