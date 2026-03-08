<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function legacySourceName(): string
    {
        return "\u{041D}\u{043E}\u{0432}\u{043E}\u{0441}\u{0442}\u{0438} Mail";
    }

    private function genericSourceName(): string
    {
        return implode('.', ['news', 'mail', 'ru']);
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('rss_feeds')
            ->where('source_name', $this->legacySourceName())
            ->update(['source_name' => '']);

        DB::table('articles')
            ->where('source_name', $this->legacySourceName())
            ->update(['source_name' => null]);

        DB::table('articles')
            ->where('author', $this->legacySourceName())
            ->update(['author' => 'Редакция']);

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
            $table->string('source_name')->default($this->legacySourceName())->change();
        });

        DB::table('rss_feeds')
            ->where('source_name', '')
            ->update(['source_name' => $this->legacySourceName()]);

        DB::table('articles')
            ->whereNull('source_name')
            ->update(['source_name' => $this->legacySourceName()]);
    }
};
