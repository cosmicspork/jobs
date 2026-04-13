<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('title')->nullable()->after('name');
            $table->json('summaries')->nullable()->after('title');
            $table->json('leadership_skills')->nullable()->after('summaries');
            $table->json('technical_depth')->nullable()->after('leadership_skills');
            $table->json('experience')->nullable()->after('technical_depth');
            $table->json('education')->nullable()->after('experience');
            $table->string('experience_years')->nullable()->after('education');
            $table->json('preferences')->nullable()->after('experience_years');
            $table->json('prompts')->nullable()->after('preferences');
            $table->boolean('is_admin')->default(false)->after('prompts');
            $table->boolean('digest_enabled')->default(true)->after('is_admin');
            $table->string('digest_time')->default('08:00')->after('digest_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'title', 'summaries', 'leadership_skills', 'technical_depth',
                'experience', 'education', 'experience_years', 'preferences',
                'prompts', 'is_admin', 'digest_enabled', 'digest_time',
            ]);
        });
    }
};
