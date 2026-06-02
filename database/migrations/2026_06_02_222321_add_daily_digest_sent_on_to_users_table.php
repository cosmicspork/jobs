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
        Schema::table('users', function (Blueprint $table) {
            // The local-timezone date a digest was last sent to this user. Lets the
            // scheduler fire once per day at the first run at/after digest_time,
            // independent of how coarsely schedule:run executes (e.g. a hibernating
            // instance that only wakes every 15 minutes).
            $table->date('daily_digest_sent_on')->nullable()->after('digest_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('daily_digest_sent_on');
        });
    }
};
