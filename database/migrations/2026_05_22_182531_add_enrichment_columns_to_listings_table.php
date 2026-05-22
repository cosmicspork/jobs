<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('enriched_at')->nullable()->index();
            $table->string('enrichment_source')->nullable();
        });

        // Existing listings predate enrichment and were never enqueued for
        // it. Mark them as already-enriched so the score gate doesn't strand
        // them. New listings from boards that require enrichment will leave
        // enriched_at null until EnrichListing runs.
        DB::table('listings')
            ->whereNull('enriched_at')
            ->update([
                'enriched_at' => DB::raw('scraped_at'),
                'enrichment_source' => 'legacy',
            ]);
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['enriched_at']);
            $table->dropColumn(['enriched_at', 'enrichment_source']);
        });
    }
};
