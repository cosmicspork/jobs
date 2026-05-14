<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_user', function (Blueprint $table) {
            $table->timestamp('digested_at')->nullable()->index();
        });

        // Pivots already scored before this column existed have presumably been
        // surfaced in past digests — backfill so adding the column doesn't make
        // them re-appear in the next one.
        DB::statement('UPDATE listing_user SET digested_at = scored_at WHERE scored_at IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('listing_user', function (Blueprint $table) {
            $table->dropIndex(['digested_at']);
            $table->dropColumn('digested_at');
        });
    }
};
