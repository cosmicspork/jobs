<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $title
 * @property string|null $summary
 * @property array<int, string>|null $skills
 * @property array<int, array<string, mixed>>|null $experience
 * @property array<int, string>|null $education
 * @property string|null $experience_years
 * @property array<string, mixed>|null $preferences
 * @property array<string, string>|null $prompts
 * @property bool $is_admin
 * @property bool $digest_enabled
 * @property string $digest_time
 * @property string $timezone
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'title',
        'summary',
        'skills',
        'experience',
        'education',
        'experience_years',
        'preferences',
        'prompts',
        'is_admin',
        'digest_enabled',
        'digest_time',
        'timezone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return BelongsToMany<Listing, $this, ListingUser, 'pivot'>
     */
    public function listings(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'listing_user')
            ->using(ListingUser::class)
            ->withPivot([
                'id', 'relevance', 'score_data', 'scored_at',
                'read_at', 'starred_at', 'shortlisted_at',
            ])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * @return HasMany<TargetProfile, $this>
     */
    public function targetProfiles(): HasMany
    {
        return $this->hasMany(TargetProfile::class)->orderBy('sort_order');
    }

    /**
     * @return Collection<int, TargetProfile>
     */
    public function activeTargets(): Collection
    {
        return $this->targetProfiles()->where('is_active', true)->get();
    }

    /**
     * Pick the best-fit target for a given listing — the active target whose pivot has
     * the highest relevance score. Falls back to the first active target if nothing scored.
     */
    public function bestTargetFor(Listing $listing): ?TargetProfile
    {
        $relevanceOrder = ['relevant' => 0, 'maybe' => 1, 'irrelevant' => 2];

        $scored = ListingUser::query()
            ->where('listing_id', $listing->id)
            ->where('user_id', $this->id)
            ->whereNotNull('relevance')
            ->with('targetProfile')
            ->get()
            ->filter(fn (ListingUser $pivot) => $pivot->targetProfile?->is_active)
            ->sortBy([
                fn ($a, $b) => ($relevanceOrder[$a->relevance->value] ?? 99) <=> ($relevanceOrder[$b->relevance->value] ?? 99),
                fn ($a, $b) => ($b->scored_at <=> $a->scored_at),
            ])
            ->first();

        if ($scored) {
            return $scored->targetProfile;
        }

        return $this->activeTargets()->first();
    }

    /**
     * @return HasMany<ApplicationQuestionSet, $this>
     */
    public function questionSets(): HasMany
    {
        return $this->hasMany(ApplicationQuestionSet::class);
    }

    /**
     * @return HasMany<AiUsage, $this>
     */
    public function aiUsages(): HasMany
    {
        return $this->hasMany(AiUsage::class);
    }

    /**
     * Get the profile data as a plain array (used by AI agents).
     *
     * @return array<string, mixed>
     */
    public function getProfileData(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'title' => $this->title,
            'summary' => $this->summary,
            'skills' => $this->skills ?? [],
            'experience' => $this->experience ?? [],
            'education' => $this->education ?? [],
            'experience_years' => $this->experience_years,
        ];
    }

    /**
     * Get the user's AI prompt for a given agent, falling back to defaults.
     */
    public function getPrompt(string $key): string
    {
        $userPrompts = $this->prompts ?? [];

        return $userPrompts[$key] ?? config("profile-defaults.prompts.{$key}", '');
    }

    /**
     * Get the board keys the user is subscribed to.
     *
     * @return array<int, string>
     */
    public function subscribedBoardKeys(): array
    {
        return DB::table('board_user')
            ->where('user_id', $this->id)
            ->pluck('board_key')
            ->all();
    }

    /**
     * @param  array<int, string>  $boardKeys
     */
    public function syncSubscribedBoards(array $boardKeys): void
    {
        DB::transaction(function () use ($boardKeys) {
            DB::table('board_user')->where('user_id', $this->id)->delete();

            if ($boardKeys === []) {
                return;
            }

            $now = now();
            DB::table('board_user')->insert(array_map(
                fn (string $key): array => [
                    'user_id' => $this->id,
                    'board_key' => $key,
                    'created_at' => $now,
                ],
                $boardKeys,
            ));
        });
    }

    /**
     * Map this user's target profiles into the flat row shape used by the
     * profile and admin user-edit forms.
     *
     * @return array<int, array<string, mixed>>
     */
    public function targetProfilesForForm(): array
    {
        return $this->targetProfiles->map(fn (TargetProfile $t): array => [
            'id' => $t->id,
            'name' => $t->name,
            'is_active' => $t->is_active,
            'positioning' => $t->positioning,
            'target_titles' => $t->target_titles ?? [],
            'remote' => $t->criterion('remote'),
            'salary_min' => $t->criterion('salary_min'),
            'locations' => $t->criterion('locations', []),
            'must_have_keywords' => $t->criterion('must_have_keywords', []),
            'avoid_keywords' => $t->criterion('avoid_keywords', []),
            'sort_order' => $t->sort_order,
        ])->all();
    }

    /**
     * Persist the target profiles posted from a profile or admin form. Updates
     * existing rows in place by id, creates new ones, and deletes any not in
     * the submitted set.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function syncTargetProfiles(array $rows): void
    {
        $keptIds = [];

        foreach ($rows as $row) {
            $attrs = [
                'name' => $row['name'],
                'positioning' => $row['positioning'] ?? null,
                'target_titles' => $row['target_titles'] ?? [],
                'criteria' => [
                    'remote' => $row['remote'] ?? false,
                    'salary_min' => isset($row['salary_min']) && $row['salary_min'] !== '' ? (int) $row['salary_min'] : null,
                    'locations' => $row['locations'] ?? [],
                    'must_have_keywords' => $row['must_have_keywords'] ?? [],
                    'avoid_keywords' => $row['avoid_keywords'] ?? [],
                ],
                'is_active' => (bool) ($row['is_active'] ?? true),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];

            $existing = ! empty($row['id'])
                ? $this->targetProfiles()->where('id', $row['id'])->first()
                : null;

            if ($existing) {
                $existing->update($attrs);
                $keptIds[] = $existing->id;

                continue;
            }

            $keptIds[] = $this->targetProfiles()->create($attrs)->id;
        }

        $this->targetProfiles()->whereNotIn('id', $keptIds)->delete();
    }

    /**
     * @return array<string, string>
     */
    public static function boardOptions(): array
    {
        return collect(config('boards'))
            ->mapWithKeys(fn (array $board, string $key): array => [$key => $board['name']])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function timezoneOptions(): array
    {
        static $options = null;

        return $options ??= collect(\DateTimeZone::listIdentifiers())
            ->mapWithKeys(fn (string $tz): array => [$tz => $tz])
            ->all();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Whether the user has filled out enough of their profile for scoring to produce useful results.
     */
    public function hasMinimumProfile(): bool
    {
        if (empty($this->title) || empty($this->summary) || empty($this->skills)) {
            return false;
        }

        return $this->hasReadyTargetProfile();
    }

    /**
     * Whether the user has at least one active target with the fields scoring needs:
     * positioning, target titles, and a remote preference set.
     */
    public function hasReadyTargetProfile(): bool
    {
        return $this->targetProfiles()
            ->where('is_active', true)
            ->whereNotNull('positioning')
            ->get()
            ->contains(fn (TargetProfile $target) => ! empty($target->target_titles)
                && isset($target->criteria['remote']));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'skills' => 'array',
            'experience' => 'array',
            'education' => 'array',
            'preferences' => 'array',
            'prompts' => 'array',
            'is_admin' => 'boolean',
            'digest_enabled' => 'boolean',
        ];
    }
}
