<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::whenTableDoesntHaveColumn('sub_categories', 'color', function (): void {
            Schema::table('sub_categories', function (Blueprint $table): void {
                $table->string('color', 7)->nullable()->after('description');
            });
        });

        Schema::whenTableDoesntHaveColumn('sub_categories', 'icon', function (): void {
            Schema::table('sub_categories', function (Blueprint $table): void {
                $table->string('icon')->nullable()->after('color');
            });
        });

        Schema::whenTableDoesntHaveIndex('sub_categories', ['category_id'], function (): void {
            Schema::table('sub_categories', function (Blueprint $table): void {
                $table->index('category_id');
            });
        });

        Schema::whenTableDoesntHaveIndex('sub_categories', ['is_active'], function (): void {
            Schema::table('sub_categories', function (Blueprint $table): void {
                $table->index('is_active');
            });
        });

        $this->backfillMetadata();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::whenTableHasIndex('sub_categories', ['category_id'], function (): void {
            Schema::table('sub_categories', function (Blueprint $table): void {
                $table->dropIndex(['category_id']);
            });
        });

        Schema::whenTableHasIndex('sub_categories', ['is_active'], function (): void {
            Schema::table('sub_categories', function (Blueprint $table): void {
                $table->dropIndex(['is_active']);
            });
        });

        Schema::whenTableHasColumn('sub_categories', 'icon', function (): void {
            Schema::table('sub_categories', function (Blueprint $table): void {
                $table->dropColumn('icon');
            });
        });

        Schema::whenTableHasColumn('sub_categories', 'color', function (): void {
            Schema::table('sub_categories', function (Blueprint $table): void {
                $table->dropColumn('color');
            });
        });
    }

    private function backfillMetadata(): void
    {
        DB::table('sub_categories')
            ->join('categories', 'categories.id', '=', 'sub_categories.category_id')
            ->select([
                'sub_categories.id',
                'sub_categories.color',
                'sub_categories.icon',
                'categories.color as category_color',
                'categories.icon as category_icon',
            ])
            ->orderBy('sub_categories.id')
            ->get()
            ->each(function (object $row): void {
                $updates = [];

                if (($row->color === null || $row->color === '') && is_string($row->category_color) && $row->category_color !== '') {
                    $updates['color'] = $row->category_color;
                }

                if (($row->icon === null || $row->icon === '') && is_string($row->category_icon) && $row->category_icon !== '') {
                    $updates['icon'] = $row->category_icon;
                }

                if ($updates === []) {
                    return;
                }

                DB::table('sub_categories')
                    ->where('id', $row->id)
                    ->update($updates);
            });
    }
};
