<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->supportsFullTextIndexes()) {
            return;
        }

        Schema::table('articles', function (Blueprint $table) {
            $table->fullText(
                ['title', 'short_description', 'full_description', 'author', 'source_name'],
                'articles_search_fulltext',
            );
        });
    }

    public function down(): void
    {
        if (! $this->supportsFullTextIndexes()) {
            return;
        }

        Schema::table('articles', function (Blueprint $table) {
            $table->dropFullText('articles_search_fulltext');
        });
    }

    private function supportsFullTextIndexes(): bool
    {
        return in_array(
            Schema::getConnection()->getDriverName(),
            ['mysql', 'pgsql'],
            true,
        );
    }
};
