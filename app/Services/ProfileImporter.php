<?php

namespace App\Services;

use App\Models\TargetProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProfileImporter
{
    /**
     * Validate + apply the import to the user, returning a summary of changes.
     *
     * @param  array<string, mixed>  $data
     * @return array{added: int, updated: int, deactivated: int}
     */
    public function import(User $user, array $data): array
    {
        $validated = $this->validate($data);

        return DB::transaction(function () use ($user, $validated): array {
            $profile = $validated['profile'] ?? [];

            $user->update([
                'summary' => $profile['summary'] ?? null,
                'skills' => $profile['skills'] ?? [],
                'experience' => $profile['experience'] ?? [],
                'education' => $profile['education'] ?? [],
                'experience_years' => $profile['experience_years'] ?? null,
                'preferences' => $profile['preferences'] ?? [],
                'prompts' => $profile['prompts'] ?? [],
                'timezone' => $profile['timezone'] ?? $user->timezone,
                'digest_enabled' => (bool) ($profile['digest_enabled'] ?? false),
                'digest_time' => $profile['digest_time'] ?? '08:00',
            ]);

            return $this->upsertTargets($user, $validated['target_profiles'] ?? []);
        });
    }

    /**
     * Compute what an import would do without persisting anything.
     *
     * @param  array<string, mixed>  $data
     * @return array{added: int, updated: int, deactivated: int}
     */
    public function preview(User $user, array $data): array
    {
        $diff = $this->diff($user, $this->validate($data)['target_profiles'] ?? []);

        return [
            'added' => $diff['added'],
            'updated' => $diff['updated'],
            'deactivated' => $diff['toDeactivate']->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function validate(array $data): array
    {
        return Validator::make($data, [
            'schema_version' => ['required', 'string', 'in:'.ProfileExporter::SCHEMA_VERSION],
            'profile' => ['nullable', 'array'],
            'profile.summary' => ['nullable', 'string', 'max:5000'],
            'profile.skills' => ['nullable', 'array'],
            'profile.skills.*' => ['string', 'max:100'],
            'profile.experience' => ['nullable', 'array'],
            'profile.experience.*.role' => ['nullable', 'string', 'max:200'],
            'profile.experience.*.company' => ['nullable', 'string', 'max:200'],
            'profile.experience.*.period' => ['nullable', 'string', 'max:100'],
            'profile.experience.*.highlights' => ['nullable', 'array'],
            'profile.education' => ['nullable', 'array'],
            'profile.experience_years' => ['nullable', 'string', 'max:50'],
            'profile.preferences' => ['nullable', 'array'],
            'profile.prompts' => ['nullable', 'array'],
            'profile.timezone' => ['nullable', 'string', 'max:100'],
            'profile.digest_enabled' => ['nullable', 'boolean'],
            'profile.digest_time' => ['nullable', 'string', 'regex:/^([01]?\d|2[0-3]):[0-5]\d$/'],
            'target_profiles' => ['nullable', 'array', 'max:20'],
            'target_profiles.*.key' => ['nullable', 'string', 'max:100'],
            'target_profiles.*.name' => ['required', 'string', 'max:200'],
            'target_profiles.*.positioning' => ['nullable', 'string', 'max:5000'],
            'target_profiles.*.target_titles' => ['nullable', 'array'],
            'target_profiles.*.criteria' => ['nullable', 'array'],
            'target_profiles.*.is_active' => ['nullable', 'boolean'],
            'target_profiles.*.sort_order' => ['nullable', 'integer'],
        ])->validate();
    }

    /**
     * @param  array<int, array<string, mixed>>  $targets
     * @return array{added: int, updated: int, deactivated: int}
     */
    protected function upsertTargets(User $user, array $targets): array
    {
        $diff = $this->diff($user, $targets);

        foreach ($targets as $row) {
            $key = $row['key'] ?? Str::slug($row['name']);

            $attrs = [
                'name' => $row['name'],
                'positioning' => $row['positioning'] ?? null,
                'target_titles' => $row['target_titles'] ?? [],
                'criteria' => $row['criteria'] ?? [],
                'is_active' => (bool) ($row['is_active'] ?? true),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];

            if ($existing = $diff['existingByKey']->get($key)) {
                $existing->update($attrs);

                continue;
            }

            $user->targetProfiles()->create($attrs);
        }

        foreach ($diff['toDeactivate'] as $target) {
            $target->update(['is_active' => false]);
        }

        return [
            'added' => $diff['added'],
            'updated' => $diff['updated'],
            'deactivated' => $diff['toDeactivate']->count(),
        ];
    }

    /**
     * Compute existing-vs-incoming target diff: which keys are new (added),
     * which match an existing target (updated), and which existing actives
     * are missing from the import (toDeactivate).
     *
     * @param  array<int, array<string, mixed>>  $targets
     * @return array{
     *     existingByKey: Collection<string, TargetProfile>,
     *     added: int,
     *     updated: int,
     *     toDeactivate: Collection<string, TargetProfile>,
     * }
     */
    protected function diff(User $user, array $targets): array
    {
        $existingByKey = $user->targetProfiles()
            ->get()
            ->keyBy(fn (TargetProfile $t) => Str::slug($t->name));

        $incomingKeys = [];
        $added = 0;
        $updated = 0;

        foreach ($targets as $row) {
            $key = $row['key'] ?? Str::slug($row['name']);
            $incomingKeys[] = $key;
            $existingByKey->has($key) ? $updated++ : $added++;
        }

        $toDeactivate = $existingByKey
            ->filter(fn (TargetProfile $t) => $t->is_active
                && ! in_array(Str::slug($t->name), $incomingKeys, true));

        return compact('existingByKey', 'added', 'updated', 'toDeactivate');
    }
}
