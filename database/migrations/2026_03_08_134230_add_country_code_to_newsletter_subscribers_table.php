<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table): void {
            if (! Schema::hasColumn('newsletter_subscribers', 'country_code')) {
                $table->string('country_code', 2)->nullable()->after('ip_address');
            }
        });

        Schema::whenTableDoesntHaveIndex('newsletter_subscribers', ['country_code'], function (): void {
            Schema::table('newsletter_subscribers', function (Blueprint $table): void {
                $table->index('country_code');
            });
        });

        Schema::whenTableDoesntHaveIndex('article_views', ['viewed_at', 'country_code'], function (): void {
            Schema::table('article_views', function (Blueprint $table): void {
                $table->index(['viewed_at', 'country_code']);
            });
        });
    }

    public function down(): void
    {
        Schema::whenTableHasIndex('article_views', ['viewed_at', 'country_code'], function (): void {
            Schema::table('article_views', function (Blueprint $table): void {
                $table->dropIndex(['viewed_at', 'country_code']);
            });
        });

        Schema::whenTableHasIndex('newsletter_subscribers', ['country_code'], function (): void {
            Schema::table('newsletter_subscribers', function (Blueprint $table): void {
                $table->dropIndex(['country_code']);
            });
        });

        Schema::table('newsletter_subscribers', function (Blueprint $table): void {
            if (Schema::hasColumn('newsletter_subscribers', 'country_code')) {
                $table->dropColumn('country_code');
            }
        });
    }
};
