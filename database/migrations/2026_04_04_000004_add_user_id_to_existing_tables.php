<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('listing_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('application_question_sets', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('listing_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('ai_usages', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('application_question_sets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('ai_usages', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
