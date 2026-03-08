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
        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('editor_id')->nullable()->after('rss_feed_id')->constrained('users')->nullOnDelete();
            $table->string('image_caption')->nullable()->after('image_url');
            $table->string('author_url')->nullable()->after('author');
            $table->enum('content_type', ['news', 'article', 'opinion', 'analysis', 'interview'])->default('news')->after('status');
            $table->boolean('is_pinned')->default(false)->after('is_breaking');
            $table->boolean('is_editors_choice')->default(false)->after('is_pinned');
            $table->boolean('is_sponsored')->default(false)->after('is_editors_choice');
            $table->tinyInteger('importance')->default(5)->after('is_sponsored');
            $table->string('meta_title')->nullable()->after('importance');
            $table->string('meta_description')->nullable()->after('meta_title');
            $table->string('canonical_url')->nullable()->after('meta_description');
            $table->json('structured_data')->nullable()->after('canonical_url');
            $table->unsignedInteger('unique_views_count')->default(0)->after('views_count');
            $table->unsignedInteger('shares_count')->default(0)->after('unique_views_count');
            $table->unsignedInteger('bookmarks_count')->default(0)->after('shares_count');
            $table->decimal('engagement_score', 8, 2)->default(0)->after('reading_time');
            $table->timestamp('last_edited_at')->nullable()->after('rss_parsed_at');

        });

        Schema::whenTableDoesntHaveIndex('articles', ['is_pinned', 'category_id'], function (): void {
            Schema::table('articles', function (Blueprint $table) {
                $table->index(['is_pinned', 'category_id']);
            });
        });

        Schema::whenTableDoesntHaveIndex('articles', ['importance'], function (): void {
            Schema::table('articles', function (Blueprint $table) {
                $table->index('importance');
            });
        });

        Schema::whenTableDoesntHaveIndex('articles', ['engagement_score'], function (): void {
            Schema::table('articles', function (Blueprint $table) {
                $table->index('engagement_score');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::whenTableHasIndex('articles', ['is_pinned', 'category_id'], function (): void {
            Schema::table('articles', function (Blueprint $table) {
                $table->dropIndex(['is_pinned', 'category_id']);
            });
        });

        Schema::whenTableHasIndex('articles', ['importance'], function (): void {
            Schema::table('articles', function (Blueprint $table) {
                $table->dropIndex(['importance']);
            });
        });

        Schema::whenTableHasIndex('articles', ['engagement_score'], function (): void {
            Schema::table('articles', function (Blueprint $table) {
                $table->dropIndex(['engagement_score']);
            });
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('editor_id');
            $table->dropColumn([
                'image_caption',
                'author_url',
                'content_type',
                'is_pinned',
                'is_editors_choice',
                'is_sponsored',
                'importance',
                'meta_title',
                'meta_description',
                'canonical_url',
                'structured_data',
                'unique_views_count',
                'shares_count',
                'bookmarks_count',
                'engagement_score',
                'last_edited_at',
            ]);
        });
    }
};
