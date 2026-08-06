<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_user', function (Blueprint $table) {
            $table->timestamp('applied_at')->nullable()->after('shortlisted_at');
            $table->string('outcome')->nullable()->after('applied_at');
            $table->timestamp('outcome_at')->nullable()->after('outcome');

            // The Applied stage orders by applied_at; the existing
            // (user_id, relevance, scored_at) index does nothing for it.
            $table->index(['user_id', 'applied_at']);
        });

        Artisan::call('listings:backfill-applied-at');
    }

    public function down(): void
    {
        Schema::table('listing_user', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'applied_at']);
            $table->dropColumn(['applied_at', 'outcome', 'outcome_at']);
        });
    }
};
