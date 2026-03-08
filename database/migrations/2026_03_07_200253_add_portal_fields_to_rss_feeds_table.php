<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rss_feeds', function (Blueprint $table) {
            $table->string('source_name')->default('')->after('url');
            $table->string('language', 5)->default('ru')->after('source_name');
            $table->boolean('auto_publish')->default(true)->after('is_active');
            $table->boolean('auto_featured')->default(false)->after('auto_publish');
            $table->integer('fetch_interval')->default(15)->after('auto_featured');
            $table->timestamp('next_parse_at')->nullable()->after('last_parsed_at');
            $table->unsignedSmallInteger('last_run_error_count')->default(0)->after('last_run_skip_count');
            $table->unsignedSmallInteger('consecutive_failures')->default(0)->after('last_run_error_count');
            $table->json('extra_settings')->nullable()->after('last_error');
        });

        Schema::whenTableDoesntHaveIndex('rss_feeds', ['is_active', 'next_parse_at'], function (): void {
            Schema::table('rss_feeds', function (Blueprint $table) {
                $table->index(['is_active', 'next_parse_at']);
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::whenTableHasIndex('rss_feeds', ['is_active', 'next_parse_at'], function (): void {
            Schema::table('rss_feeds', function (Blueprint $table) {
                $table->dropIndex(['is_active', 'next_parse_at']);
            });
        });

        Schema::table('rss_feeds', function (Blueprint $table) {
            $table->dropColumn([
                'source_name',
                'language',
                'auto_publish',
                'auto_featured',
                'fetch_interval',
                'next_parse_at',
                'last_run_error_count',
                'consecutive_failures',
                'extra_settings',
            ]);
        });
    }
};
