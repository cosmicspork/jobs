<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->string('relevance')->nullable()->after('raw_data');
        });

        DB::table('listings')->whereNotNull('score')->update([
            'relevance' => DB::raw("CASE WHEN score >= 70 THEN 'relevant' WHEN score >= 50 THEN 'maybe' ELSE 'irrelevant' END"),
        ]);

        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('listings_score_scored_at_index');
            $table->dropColumn('score');
            $table->index(['relevance', 'scored_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->integer('score')->nullable()->after('raw_data');
            $table->dropIndex('listings_relevance_scored_at_index');
            $table->dropColumn('relevance');
            $table->index(['score', 'scored_at']);
        });
    }
};
