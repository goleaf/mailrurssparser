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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('sub_category_id')
                ->nullable()
                ->constrained('sub_categories')
                ->nullOnDelete();
            $table->foreignId('rss_feed_id')
                ->nullable()
                ->constrained('rss_feeds')
                ->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('source_url', 2048)->nullable();
            $table->string('source_guid', 2048)->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->text('short_description')->nullable();
            $table->longText('full_description')->nullable();
            $table->longText('rss_content')->nullable();
            $table->string('author')->nullable();
            $table->string('source_name')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('published');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_breaking')->default(false);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedSmallInteger('reading_time')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('rss_parsed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['category_id', 'status']);
            $table->index('is_featured');
            $table->index('is_breaking');
            $table->index('source_guid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
