<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('listings_relevance_scored_at_index');
            $table->dropColumn([
                'relevance',
                'score_data',
                'scored_at',
                'read_at',
                'starred_at',
                'shortlisted_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->string('relevance')->nullable();
            $table->json('score_data')->nullable();
            $table->timestamp('scored_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('starred_at')->nullable();
            $table->timestamp('shortlisted_at')->nullable();
            $table->index(['relevance', 'scored_at'], 'listings_relevance_scored_at_index');
        });
    }
};
