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
        Schema::table('article_views', function (Blueprint $table) {
            $table->string('ip_hash', 64)->nullable()->after('article_id');
            $table->string('session_hash', 64)->nullable()->after('ip_hash');
            $table->string('country_code', 2)->nullable()->after('session_hash');
            $table->string('device_type', 20)->nullable()->after('country_code');
            $table->string('referrer_type', 30)->nullable()->after('device_type');
            $table->string('referrer_domain')->nullable()->after('referrer_type');

            $table->index('device_type');
            $table->index('session_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article_views', function (Blueprint $table) {
            $table->dropIndex(['device_type']);
            $table->dropIndex(['session_hash']);
            $table->dropColumn([
                'ip_hash',
                'session_hash',
                'country_code',
                'device_type',
                'referrer_type',
                'referrer_domain',
            ]);
        });
    }
};
