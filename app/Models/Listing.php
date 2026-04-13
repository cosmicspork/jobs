<?php

namespace App\Models;

use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'title',
        'company',
        'url',
        'description',
        'salary_min',
        'salary_max',
        'remote',
        'board',
        'raw_data',
        'scraped_at',
    ];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'listing_user')
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

    public function companyName(): string
    {
        return $this->company ?? 'Unknown';
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
        ];
    }
}
