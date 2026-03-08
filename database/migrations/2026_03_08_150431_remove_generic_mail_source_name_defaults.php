<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function genericSourceName(): string
    {
        return implode('.', ['news', 'mail', 'ru']);
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('articles')
            ->where('source_name', $this->genericSourceName())
            ->update(['source_name' => null]);

        DB::table('rss_feeds')
            ->where('source_name', $this->genericSourceName())
            ->update(['source_name' => '']);

        Schema::table('rss_feeds', function (Blueprint $table) {
            $table->string('source_name')->default('')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rss_feeds', function (Blueprint $table) {
            $table->string('source_name')->default($this->genericSourceName())->change();
        });

        DB::table('rss_feeds')
            ->where('source_name', '')
            ->update(['source_name' => $this->genericSourceName()]);
    }
};
