<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('summary')->nullable()->after('title');
            $table->json('skills')->nullable()->after('summary');
        });

        foreach (DB::table('users')->get() as $user) {
            $summaries = $user->summaries ? json_decode($user->summaries, true) : [];
            $leadership = $user->leadership_skills ? json_decode($user->leadership_skills, true) : [];
            $technical = $user->technical_depth ? json_decode($user->technical_depth, true) : [];
            $preferences = $user->preferences ? json_decode($user->preferences, true) : [];

            $summary = $summaries['em'] ?? $summaries['ic'] ?? null;

            $skills = [];
            foreach ((array) $leadership as $s) {
                $skills[] = (string) $s;
            }
            foreach ((array) $technical as $value) {
                if (is_array($value)) {
                    foreach ($value as $s) {
                        $skills[] = (string) $s;
                    }
                } else {
                    foreach (explode(',', (string) $value) as $s) {
                        $trimmed = trim($s);
                        if ($trimmed !== '') {
                            $skills[] = $trimmed;
                        }
                    }
                }
            }
            $skills = array_values(array_unique(array_filter($skills)));

            $hasEm = ! empty($summaries['em'] ?? '');
            $hasIc = ! empty($summaries['ic'] ?? '');
            $roleType = match (true) {
                $hasEm && $hasIc => 'both',
                $hasEm => 'em',
                $hasIc => 'ic',
                default => 'both',
            };
            $preferences['role_type'] = $preferences['role_type'] ?? $roleType;

            DB::table('users')->where('id', $user->id)->update([
                'summary' => $summary,
                'skills' => json_encode($skills),
                'preferences' => json_encode($preferences),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['summaries', 'leadership_skills', 'technical_depth']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('summaries')->nullable();
            $table->json('leadership_skills')->nullable();
            $table->json('technical_depth')->nullable();
            $table->dropColumn(['summary', 'skills']);
        });
    }
};
