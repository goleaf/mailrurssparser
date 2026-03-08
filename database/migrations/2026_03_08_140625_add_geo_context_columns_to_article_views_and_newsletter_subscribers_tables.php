<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_views', function (Blueprint $table): void {
            if (! Schema::hasColumn('article_views', 'timezone')) {
                $table->string('timezone')->nullable()->after('country_code');
            }

            if (! Schema::hasColumn('article_views', 'locale')) {
                $table->string('locale', 10)->nullable()->after('timezone');
            }
        });

        Schema::table('newsletter_subscribers', function (Blueprint $table): void {
            if (! Schema::hasColumn('newsletter_subscribers', 'timezone')) {
                $table->string('timezone')->nullable()->after('country_code');
            }

            if (! Schema::hasColumn('newsletter_subscribers', 'locale')) {
                $table->string('locale', 10)->nullable()->after('timezone');
            }
        });

        Schema::whenTableDoesntHaveIndex('article_views', ['viewed_at', 'timezone'], function (): void {
            Schema::table('article_views', function (Blueprint $table): void {
                $table->index(['viewed_at', 'timezone']);
            });
        });

        Schema::whenTableDoesntHaveIndex('article_views', ['viewed_at', 'locale'], function (): void {
            Schema::table('article_views', function (Blueprint $table): void {
                $table->index(['viewed_at', 'locale']);
            });
        });

        Schema::whenTableDoesntHaveIndex('newsletter_subscribers', ['timezone'], function (): void {
            Schema::table('newsletter_subscribers', function (Blueprint $table): void {
                $table->index('timezone');
            });
        });

        Schema::whenTableDoesntHaveIndex('newsletter_subscribers', ['locale'], function (): void {
            Schema::table('newsletter_subscribers', function (Blueprint $table): void {
                $table->index('locale');
            });
        });
    }

    public function down(): void
    {
        Schema::whenTableHasIndex('newsletter_subscribers', ['locale'], function (): void {
            Schema::table('newsletter_subscribers', function (Blueprint $table): void {
                $table->dropIndex(['locale']);
            });
        });

        Schema::whenTableHasIndex('newsletter_subscribers', ['timezone'], function (): void {
            Schema::table('newsletter_subscribers', function (Blueprint $table): void {
                $table->dropIndex(['timezone']);
            });
        });

        Schema::whenTableHasIndex('article_views', ['viewed_at', 'locale'], function (): void {
            Schema::table('article_views', function (Blueprint $table): void {
                $table->dropIndex(['viewed_at', 'locale']);
            });
        });

        Schema::whenTableHasIndex('article_views', ['viewed_at', 'timezone'], function (): void {
            Schema::table('article_views', function (Blueprint $table): void {
                $table->dropIndex(['viewed_at', 'timezone']);
            });
        });

        Schema::table('newsletter_subscribers', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('newsletter_subscribers', 'timezone') ? 'timezone' : null,
                Schema::hasColumn('newsletter_subscribers', 'locale') ? 'locale' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('article_views', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('article_views', 'timezone') ? 'timezone' : null,
                Schema::hasColumn('article_views', 'locale') ? 'locale' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
