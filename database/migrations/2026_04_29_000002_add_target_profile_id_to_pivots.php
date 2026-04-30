<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_user', function (Blueprint $table) {
            $table->ulid('target_profile_id')->nullable()->after('user_id');
            $table->foreign('target_profile_id')
                ->references('id')->on('target_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->ulid('target_profile_id')->nullable()->after('user_id');
            $table->foreign('target_profile_id')
                ->references('id')->on('target_profiles')
                ->nullOnDelete();
        });

        Schema::table('application_question_sets', function (Blueprint $table) {
            $table->ulid('target_profile_id')->nullable()->after('user_id');
            $table->foreign('target_profile_id')
                ->references('id')->on('target_profiles')
                ->nullOnDelete();
        });

        $this->backfill();

        Schema::table('listing_user', function (Blueprint $table) {
            $table->dropUnique(['listing_id', 'user_id']);
            $table->unique(['listing_id', 'target_profile_id']);
        });

        Schema::table('listing_user', function (Blueprint $table) {
            $table->ulid('target_profile_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('listing_user', function (Blueprint $table) {
            $table->ulid('target_profile_id')->nullable()->change();
            $table->dropUnique(['listing_id', 'target_profile_id']);
            $table->unique(['listing_id', 'user_id']);
            $table->dropForeign(['target_profile_id']);
            $table->dropColumn('target_profile_id');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['target_profile_id']);
            $table->dropColumn('target_profile_id');
        });

        Schema::table('application_question_sets', function (Blueprint $table) {
            $table->dropForeign(['target_profile_id']);
            $table->dropColumn('target_profile_id');
        });
    }

    private function backfill(): void
    {
        $now = now();

        foreach (DB::table('users')->get() as $user) {
            $preferences = $user->preferences ? json_decode($user->preferences, true) : [];
            $roleType = $preferences['role_type'] ?? 'both';

            [$targetName, $targetTitles] = match ($roleType) {
                'em' => ['Engineering Management', ['Engineering Manager', 'Director of Engineering', 'Head of Engineering']],
                'ic' => ['IC Software Engineering', ['Senior Software Engineer', 'Staff Engineer', 'Principal Engineer']],
                default => ['Engineering roles', []],
            };

            $criteria = [
                'remote' => $preferences['remote'] ?? null,
                'salary_min' => $preferences['salary_min'] ?? null,
                'locations' => $preferences['locations'] ?? [],
                'must_have_keywords' => [],
                'avoid_keywords' => [],
            ];

            $targetId = (string) Str::ulid();

            DB::table('target_profiles')->insert([
                'id' => $targetId,
                'user_id' => $user->id,
                'name' => $targetName,
                'positioning' => $user->summary,
                'target_titles' => json_encode($targetTitles),
                'criteria' => json_encode($criteria),
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('listing_user')
                ->where('user_id', $user->id)
                ->update(['target_profile_id' => $targetId]);

            DB::table('applications')
                ->where('user_id', $user->id)
                ->update(['target_profile_id' => $targetId]);

            DB::table('application_question_sets')
                ->where('user_id', $user->id)
                ->update(['target_profile_id' => $targetId]);
        }
    }
};
