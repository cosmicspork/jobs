<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'title',
        'summaries',
        'leadership_skills',
        'technical_depth',
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
     * @return BelongsToMany<Listing, $this>
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
     * Get the profile data in the same shape as the old config('profile') format.
     *
     * @return array<string, mixed>
     */
    public function getProfileData(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'summaries' => $this->summaries ?? [],
            'leadership_skills' => $this->leadership_skills ?? [],
            'technical_depth' => $this->technical_depth ?? [],
            'experience' => $this->experience ?? [],
            'education' => $this->education ?? [],
            'experience_years' => $this->experience_years,
            'preferences' => $this->preferences ?? [],
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
        return ! empty($this->title)
            && ! empty($this->technical_depth)
            && isset($this->preferences['remote'])
            && (! empty($this->summaries['em']) || ! empty($this->summaries['ic']));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'summaries' => 'array',
            'leadership_skills' => 'array',
            'technical_depth' => 'array',
            'experience' => 'array',
            'education' => 'array',
            'preferences' => 'array',
            'prompts' => 'array',
            'is_admin' => 'boolean',
            'digest_enabled' => 'boolean',
        ];
    }
}
