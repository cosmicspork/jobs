<?php

namespace App\Models;

use App\Relevance;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $listing_id
 * @property int $user_id
 * @property string|null $target_profile_id
 * @property Relevance|null $relevance
 * @property array<string, mixed>|null $score_data
 * @property Carbon|null $scored_at
 * @property Carbon|null $read_at
 * @property Carbon|null $starred_at
 * @property Carbon|null $shortlisted_at
 */
class ListingUser extends Pivot
{
    use HasUlids;

    public $incrementing = false;

    protected $table = 'listing_user';

    protected $fillable = [
        'listing_id',
        'user_id',
        'target_profile_id',
        'relevance',
        'score_data',
        'scored_at',
        'read_at',
        'starred_at',
        'shortlisted_at',
    ];

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<TargetProfile, $this>
     */
    public function targetProfile(): BelongsTo
    {
        return $this->belongsTo(TargetProfile::class);
    }

    public function toggleRead(): void
    {
        static::query()
            ->where('listing_id', $this->listing_id)
            ->where('user_id', $this->user_id)
            ->update(['read_at' => $this->read_at ? null : now()]);
    }

    public function toggleStarred(): void
    {
        static::query()
            ->where('listing_id', $this->listing_id)
            ->where('user_id', $this->user_id)
            ->update(['starred_at' => $this->starred_at ? null : now()]);
    }

    public static function forUserListing(int $userId, string $listingId): ?static
    {
        return static::query()
            ->where('listing_id', $listingId)
            ->where('user_id', $userId)
            ->orderByRaw("CASE relevance WHEN 'relevant' THEN 0 WHEN 'maybe' THEN 1 WHEN 'irrelevant' THEN 2 ELSE 99 END")
            ->orderByDesc('scored_at')
            ->first();
    }

    public function shortlist(): void
    {
        static::query()
            ->where('listing_id', $this->listing_id)
            ->where('user_id', $this->user_id)
            ->update(['shortlisted_at' => now()]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relevance' => Relevance::class,
            'score_data' => 'array',
            'scored_at' => 'datetime',
            'read_at' => 'datetime',
            'starred_at' => 'datetime',
            'shortlisted_at' => 'datetime',
        ];
    }
}
