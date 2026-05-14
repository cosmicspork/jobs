<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_user', function (Blueprint $table) {
            $table->dropForeign(['target_profile_id']);
            $table->foreign('target_profile_id')
                ->references('id')->on('target_profiles')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('listing_user', function (Blueprint $table) {
            $table->dropForeign(['target_profile_id']);
            $table->foreign('target_profile_id')
                ->references('id')->on('target_profiles')
                ->cascadeOnDelete();
        });
    }
};
