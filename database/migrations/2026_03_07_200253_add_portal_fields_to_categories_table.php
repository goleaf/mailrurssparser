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
        Schema::table('categories', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('icon');
            $table->string('meta_description')->nullable()->after('meta_title');
            $table->boolean('show_in_menu')->default(true)->after('is_active');
            $table->unsignedInteger('articles_count_cache')->default(0)->after('show_in_menu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn([
                'meta_title',
                'meta_description',
                'show_in_menu',
                'articles_count_cache',
            ]);
        });
    }
};
