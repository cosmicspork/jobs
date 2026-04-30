<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('users')->get() as $user) {
            $preferences = $user->preferences ? json_decode($user->preferences, true) : [];

            unset(
                $preferences['role_type'],
                $preferences['remote'],
                $preferences['salary_min'],
                $preferences['locations'],
            );

            DB::table('users')->where('id', $user->id)->update([
                'preferences' => $preferences === [] ? null : json_encode($preferences),
            ]);
        }
    }

    public function down(): void
    {
        // Lossy by design — preferences moved to target_profiles.criteria.
    }
};
