<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rss_feeds', function (Blueprint $table) {
            $table
                ->foreignId('curator_logo_id')
                ->nullable()
                ->constrained('curator')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rss_feeds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('curator_logo_id');
        });
    }
};
