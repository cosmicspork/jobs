<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $hasLegacyData = DB::table('listings')->exists()
            || DB::table('applications')->exists()
            || DB::table('application_question_sets')->exists()
            || DB::table('ai_usages')->exists();

        if ($hasLegacyData) {
            $profile = config('profile', []);

            $userId = DB::table('users')->insertGetId([
                'name' => $profile['name'] ?? env('PROFILE_NAME', 'Admin'),
                'email' => $profile['email'] ?? env('PROFILE_EMAIL', 'admin@example.com'),
                'password' => Hash::make(Str::random(32)),
                'title' => null,
                'summaries' => json_encode($profile['summaries'] ?? []),
                'leadership_skills' => json_encode($profile['leadership_skills'] ?? []),
                'technical_depth' => json_encode($profile['technical_depth'] ?? []),
                'experience' => json_encode($profile['experience'] ?? []),
                'education' => json_encode($profile['education'] ?? []),
                'is_admin' => true,
                'digest_enabled' => true,
                'digest_time' => '08:00',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Copy per-user listing data into listing_user pivot
            $listings = DB::table('listings')
                ->whereNotNull('scored_at')
                ->orWhereNotNull('read_at')
                ->orWhereNotNull('starred_at')
                ->orWhereNotNull('shortlisted_at')
                ->get();

            foreach ($listings as $listing) {
                DB::table('listing_user')->insert([
                    'id' => Str::ulid()->toBase32(),
                    'listing_id' => $listing->id,
                    'user_id' => $userId,
                    'relevance' => $listing->relevance,
                    'score_data' => $listing->score_data,
                    'scored_at' => $listing->scored_at,
                    'read_at' => $listing->read_at,
                    'starred_at' => $listing->starred_at,
                    'shortlisted_at' => $listing->shortlisted_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Also create listing_user rows for scored listings that didn't match the OR above
            $remainingListings = DB::table('listings')
                ->whereNotNull('relevance')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('listing_user')
                        ->whereColumn('listing_user.listing_id', 'listings.id');
                })
                ->get();

            foreach ($remainingListings as $listing) {
                DB::table('listing_user')->insert([
                    'id' => Str::ulid()->toBase32(),
                    'listing_id' => $listing->id,
                    'user_id' => $userId,
                    'relevance' => $listing->relevance,
                    'score_data' => $listing->score_data,
                    'scored_at' => $listing->scored_at,
                    'read_at' => $listing->read_at,
                    'starred_at' => $listing->starred_at,
                    'shortlisted_at' => $listing->shortlisted_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Set user_id on all existing applications and question sets
            DB::table('applications')->whereNull('user_id')->update(['user_id' => $userId]);
            DB::table('application_question_sets')->whereNull('user_id')->update(['user_id' => $userId]);
            DB::table('ai_usages')->whereNull('user_id')->update(['user_id' => $userId]);

            // Subscribe user to all boards
            $boards = array_keys(config('boards', []));
            foreach ($boards as $boardKey) {
                DB::table('board_user')->insert([
                    'user_id' => $userId,
                    'board_key' => $boardKey,
                    'created_at' => now(),
                ]);
            }
        }

        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('application_question_sets', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // Make user_id nullable again
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });

        Schema::table('application_question_sets', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });

        DB::table('listing_user')->truncate();
        DB::table('board_user')->truncate();

        // Delete the seeded admin user
        DB::table('users')->where('is_admin', true)->limit(1)->delete();
    }
};
