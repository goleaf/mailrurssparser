<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('measurable_type')->nullable();
            $table->unsignedBigInteger('measurable_id')->nullable();
            $table->dateTime('bucket_start');
            $table->date('bucket_date');
            $table->string('fingerprint')->unique();
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();

            $table->index(['name', 'bucket_start']);
            $table->index(['category', 'bucket_start']);
            $table->index(['measurable_type', 'measurable_id', 'name']);
            $table->index(['bucket_date', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};
