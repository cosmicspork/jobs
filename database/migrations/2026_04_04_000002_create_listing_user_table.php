<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_user', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('listing_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('relevance')->nullable();
            $table->json('score_data')->nullable();
            $table->timestamp('scored_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('starred_at')->nullable();
            $table->timestamp('shortlisted_at')->nullable();
            $table->timestamps();

            $table->foreign('listing_id')->references('id')->on('listings')->cascadeOnDelete();
            $table->unique(['listing_id', 'user_id']);
            $table->index(['user_id', 'relevance', 'scored_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_user');
    }
};
