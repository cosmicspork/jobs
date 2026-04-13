<?php

namespace App\Models;

use App\Relevance;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ListingUser extends Pivot
{
    use HasUlids;

    public $incrementing = false;

    protected $table = 'listing_user';

    protected $fillable = [
        'listing_id',
        'user_id',
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

    public function toggleRead(): void
    {
        $this->update(['read_at' => $this->read_at ? null : now()]);
    }

    public function toggleStarred(): void
    {
        $this->update(['starred_at' => $this->starred_at ? null : now()]);
    }

    public static function forUserListing(int $userId, string $listingId): ?static
    {
        return static::query()
            ->where('listing_id', $listingId)
            ->where('user_id', $userId)
            ->first();
    }

    public function shortlist(): void
    {
        $this->update(['shortlisted_at' => now()]);
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
