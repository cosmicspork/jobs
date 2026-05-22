<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->json('resume_content')->nullable();
            $table->json('cover_letter_content')->nullable();
            $table->dropColumn(['resume_path', 'cover_letter_path']);
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('resume_path')->nullable();
            $table->string('cover_letter_path')->nullable();
            $table->dropColumn(['resume_content', 'cover_letter_content']);
        });
    }
};
