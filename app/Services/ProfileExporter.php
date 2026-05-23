<?php

namespace App\Services;

use App\Models\TargetProfile;
use App\Models\User;
use Illuminate\Support\Str;

class ProfileExporter
{
    public const SCHEMA_VERSION = '1';

    /**
     * Build the round-trippable profile JSON shape for the given user.
     *
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $user->loadMissing('targetProfiles');

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'profile' => [
                'summary' => $user->summary,
                'skills' => $user->skills ?? [],
                'experience' => $user->experience ?? [],
                'education' => $user->education ?? [],
                'timezone' => $user->timezone,
                'digest_enabled' => $user->digest_enabled,
                'digest_time' => $user->digest_time,
            ],
            'target_profiles' => $user->targetProfiles
                ->map(fn (TargetProfile $t): array => [
                    'key' => Str::slug($t->name),
                    'name' => $t->name,
                    'positioning' => $t->positioning,
                    'target_titles' => $t->target_titles ?? [],
                    'criteria' => $t->criteria ?? [],
                    'is_active' => $t->is_active,
                    'sort_order' => $t->sort_order,
                ])
                ->values()
                ->all(),
        ];
    }

    public function filename(User $user): string
    {
        $slug = Str::slug($user->name) ?: 'user-'.$user->id;

        return "profile-{$slug}-".now()->format('Y-m-d').'.json';
    }
}
