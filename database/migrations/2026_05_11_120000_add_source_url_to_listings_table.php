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
            $table->string('source_url', 2048)->nullable()->after('url');
        });

        DB::statement('UPDATE listings SET source_url = url WHERE source_url IS NULL');

        Schema::table('listings', function (Blueprint $table) {
            $table->string('source_url', 2048)->nullable(false)->change();
            $table->dropUnique(['url']);
            $table->unique('source_url');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropUnique(['source_url']);
            $table->unique('url');
            $table->dropColumn('source_url');
        });
    }
};
