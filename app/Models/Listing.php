<?php

namespace App\Models;

use App\Relevance;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Joined-pivot virtual properties: ListingsTable, ListingInfolist, and
 * SendDailyDigest hydrate Listing instances with columns aliased from
 * `listing_user`. These are not real Listing columns.
 *
 * @property string|null $pivot_id
 * @property Relevance|null $relevance
 * @property array<string, mixed>|null $score_data
 * @property Carbon|null $scored_at
 * @property Carbon|null $read_at
 * @property Carbon|null $starred_at
 * @property Carbon|null $shortlisted_at
 * @property Carbon|null $dismissed_at
 * @property string|null $target_profile_id
 * @property string|null $target_name
 * @property int $applications_count
 */
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'title',
        'company',
        'url',
        'source_url',
        'description',
        'salary_min',
        'salary_max',
        'remote',
        'board',
        'raw_data',
        'scraped_at',
        'created_by_user_id',
    ];

    /**
     * @return BelongsToMany<User, $this, ListingUser, 'pivot'>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'listing_user')
            ->using(ListingUser::class)
            ->withPivot([
                'id', 'relevance', 'score_data', 'scored_at',
                'read_at', 'starred_at', 'shortlisted_at', 'dismissed_at',
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

    public function companyName(): string
    {
        return $this->company ?? 'Unknown';
    }

    /**
     * Shape used to inline listing data into AI agent prompts.
     *
     * @return array<string, mixed>
     */
    public function toAgentPayload(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'company' => $this->company,
            'description' => $this->description,
            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,
            'remote' => $this->remote,
            'url' => $this->url,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'remote' => 'boolean',
            'scraped_at' => 'datetime',
            'relevance' => Relevance::class,
            'scored_at' => 'datetime',
            'read_at' => 'datetime',
            'starred_at' => 'datetime',
            'shortlisted_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'score_data' => 'array',
        ];
    }
}
