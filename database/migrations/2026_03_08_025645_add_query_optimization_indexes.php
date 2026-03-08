<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::whenTableDoesntHaveIndex('article_views', ['article_id', 'ip_hash', 'viewed_at'], function (): void {
            Schema::table('article_views', function (Blueprint $table): void {
                $table->index(
                    ['article_id', 'ip_hash', 'viewed_at'],
                    'article_views_article_id_ip_hash_viewed_at_index',
                );
            });
        });

        Schema::whenTableDoesntHaveIndex('article_views', ['article_id', 'session_hash', 'viewed_at'], function (): void {
            Schema::table('article_views', function (Blueprint $table): void {
                $table->index(
                    ['article_id', 'session_hash', 'viewed_at'],
                    'article_views_article_id_session_hash_viewed_at_index',
                );
            });
        });

        Schema::whenTableDoesntHaveIndex('bookmarks', ['session_hash', 'created_at'], function (): void {
            Schema::table('bookmarks', function (Blueprint $table): void {
                $table->index(
                    ['session_hash', 'created_at'],
                    'bookmarks_session_hash_created_at_index',
                );
            });
        });

        Schema::whenTableDoesntHaveIndex('rss_parse_logs', ['started_at'], function (): void {
            Schema::table('rss_parse_logs', function (Blueprint $table): void {
                $table->index('started_at', 'rss_parse_logs_started_at_index');
            });
        });

        Schema::whenTableDoesntHaveIndex('rss_parse_logs', ['rss_feed_id', 'started_at'], function (): void {
            Schema::table('rss_parse_logs', function (Blueprint $table): void {
                $table->index(
                    ['rss_feed_id', 'started_at'],
                    'rss_parse_logs_rss_feed_id_started_at_index',
                );
            });
        });

        Schema::whenTableDoesntHaveIndex('rss_parse_logs', ['success', 'started_at'], function (): void {
            Schema::table('rss_parse_logs', function (Blueprint $table): void {
                $table->index(
                    ['success', 'started_at'],
                    'rss_parse_logs_success_started_at_index',
                );
            });
        });
    }

    public function down(): void
    {
        Schema::whenTableHasIndex('rss_parse_logs', ['success', 'started_at'], function (): void {
            Schema::table('rss_parse_logs', function (Blueprint $table): void {
                $table->dropIndex('rss_parse_logs_success_started_at_index');
            });
        });

        Schema::whenTableHasIndex('rss_parse_logs', ['rss_feed_id', 'started_at'], function (): void {
            Schema::table('rss_parse_logs', function (Blueprint $table): void {
                $table->dropIndex('rss_parse_logs_rss_feed_id_started_at_index');
            });
        });

        Schema::whenTableHasIndex('rss_parse_logs', ['started_at'], function (): void {
            Schema::table('rss_parse_logs', function (Blueprint $table): void {
                $table->dropIndex('rss_parse_logs_started_at_index');
            });
        });

        Schema::whenTableHasIndex('bookmarks', ['session_hash', 'created_at'], function (): void {
            Schema::table('bookmarks', function (Blueprint $table): void {
                $table->dropIndex('bookmarks_session_hash_created_at_index');
            });
        });

        Schema::whenTableHasIndex('article_views', ['article_id', 'session_hash', 'viewed_at'], function (): void {
            Schema::table('article_views', function (Blueprint $table): void {
                $table->dropIndex('article_views_article_id_session_hash_viewed_at_index');
            });
        });

        Schema::whenTableHasIndex('article_views', ['article_id', 'ip_hash', 'viewed_at'], function (): void {
            Schema::table('article_views', function (Blueprint $table): void {
                $table->dropIndex('article_views_article_id_ip_hash_viewed_at_index');
            });
        });
    }
};
