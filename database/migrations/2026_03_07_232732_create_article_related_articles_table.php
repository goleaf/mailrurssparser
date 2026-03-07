<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_related_articles', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_article_id')->constrained('articles')->cascadeOnDelete();
            $table->unsignedInteger('score')->default(0);
            $table->unsignedTinyInteger('shared_tags_count')->default(0);
            $table->unsignedTinyInteger('shared_terms_count')->default(0);
            $table->boolean('same_category')->default(false);
            $table->boolean('same_sub_category')->default(false);
            $table->boolean('same_content_type')->default(false);
            $table->boolean('same_author')->default(false);
            $table->boolean('same_source')->default(false);
            $table->timestamps();

            $table->unique(['article_id', 'related_article_id']);
            $table->index(['article_id', 'score']);
            $table->index('related_article_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_related_articles');
    }
};
