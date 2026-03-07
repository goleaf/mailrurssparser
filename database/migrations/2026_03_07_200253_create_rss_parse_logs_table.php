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
        Schema::create('rss_parse_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rss_feed_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedSmallInteger('new_count')->default(0);
            $table->unsignedSmallInteger('skip_count')->default(0);
            $table->unsignedSmallInteger('error_count')->default(0);
            $table->unsignedSmallInteger('total_items')->default(0);
            $table->integer('duration_ms')->nullable();
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->json('item_errors')->nullable();
            $table->string('triggered_by', 30)->default('scheduler');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rss_parse_logs');
    }
};
