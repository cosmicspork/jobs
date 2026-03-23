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
        Schema::create('listings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->string('company');
            $table->string('url')->unique();
            $table->text('description');
            $table->integer('salary_min')->nullable();
            $table->integer('salary_max')->nullable();
            $table->boolean('remote')->default(false);
            $table->string('board');
            $table->json('raw_data')->nullable();
            $table->integer('score')->nullable();
            $table->json('score_data')->nullable();
            $table->timestamp('scored_at')->nullable();
            $table->timestamp('scraped_at');
            $table->timestamps();

            $table->index(['score', 'scored_at']);
            $table->index('board');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
