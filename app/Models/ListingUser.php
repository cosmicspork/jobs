<?php

namespace App\Models;

use App\ApplicationOutcome;
use App\Relevance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
 * @property Carbon|null $applied_at
 * @property ApplicationOutcome|null $outcome
 * @property Carbon|null $outcome_at
 * @property Carbon|null $dismissed_at
 * @property Carbon|null $digested_at
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
        'applied_at',
        'outcome',
        'outcome_at',
        'dismissed_at',
        'digested_at',
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
        $this->updateAllForListingUser(['read_at' => $this->read_at ? null : now()]);
    }

    public function toggleStarred(): void
    {
        $this->updateAllForListingUser(['starred_at' => $this->starred_at ? null : now()]);
    }

    public function toggleShortlisted(): void
    {
        $this->updateAllForListingUser(['shortlisted_at' => $this->shortlisted_at ? null : now()]);
    }

    public function toggleDismissed(): void
    {
        $this->updateAllForListingUser(['dismissed_at' => $this->dismissed_at ? null : now()]);
    }

    /**
     * Applying is recorded here, not by the existence of an Application row —
     * the AI workspace is optional and most applications never involve it.
     * Un-applying clears the outcome, which is meaningless without it.
     */
    public function toggleApplied(): void
    {
        $this->updateAllForListingUser($this->applied_at
            ? ['applied_at' => null, 'outcome' => null, 'outcome_at' => null]
            : ['applied_at' => now()]);
    }

    public function setOutcome(?ApplicationOutcome $outcome): void
    {
        $this->updateAllForListingUser([
            'outcome' => $outcome?->value,
            'outcome_at' => $outcome ? now() : null,
        ]);
    }

    /**
     * Promoting variants used by the reading room. Stages are exclusive, so
     * advancing has to also mark the listing read or it never leaves the Inbox.
     * The plain toggles stay for the table's icon columns, which are a
     * different gesture: flagging in place, not moving through the pipeline.
     */
    public function star(): void
    {
        $this->updateAllForListingUser([
            'starred_at' => now(),
            'read_at' => $this->read_at ?? now(),
        ]);
    }

    /** Shortlisting implies read, but not starred — starring is its own signal. */
    public function shortlist(): void
    {
        $this->updateAllForListingUser([
            'shortlisted_at' => now(),
            'read_at' => $this->read_at ?? now(),
        ]);
    }

    public static function forUserListing(int $userId, string $listingId): ?static
    {
        return static::query()
            ->where('listing_id', $listingId)
            ->where('user_id', $userId)
            ->orderByRaw(self::orderByRelevanceSql())
            ->orderByDesc('scored_at')
            ->first();
    }

    /**
     * SQL fragment that orders pivots best-match-first: relevant, then maybe,
     * then irrelevant, then unscored. Centralized so callers don't drift.
     */
    public static function orderByRelevanceSql(string $column = 'relevance'): string
    {
        return "CASE {$column} WHEN 'relevant' THEN 0 WHEN 'maybe' THEN 1 WHEN 'irrelevant' THEN 2 ELSE 99 END";
    }

    /**
     * Correlated subquery picking the single best-relevance pivot per listing.
     * There is one pivot per (listing, target profile), so every query over
     * listings has to collapse them or it fans out one row per target.
     */
    public static function bestPivotIdSubquery(int $userId): QueryBuilder
    {
        return DB::table('listing_user as inner_lu')
            ->select('inner_lu.id')
            ->whereColumn('inner_lu.listing_id', 'listings.id')
            ->where('inner_lu.user_id', $userId)
            ->orderByRaw(self::orderByRelevanceSql('inner_lu.relevance'))
            ->orderByDesc('inner_lu.scored_at')
            ->limit(1);
    }

    /**
     * Constrain a query to one pipeline stage. Stages are exclusive: each one
     * means "currently here", not "ever flagged", so a listing drains forward
     * as it is acted on and appears in exactly one tab.
     *
     * Works on both the pivot table directly (counts, prefix '') and a listings
     * query joined to it (tables and the reading room, prefix 'listing_user.').
     *
     * @param  Builder<Listing>|Builder<self>|QueryBuilder  $query
     */
    public static function applyStage(Builder|QueryBuilder $query, string $stage, string $prefix = ''): void
    {
        $col = fn (string $column): string => $prefix.$column;

        match ($stage) {
            'inbox' => $query
                ->whereNull($col('read_at'))
                ->whereIn($col('relevance'), [Relevance::Relevant, Relevance::Maybe])
                ->whereNull($col('starred_at'))
                ->whereNull($col('shortlisted_at'))
                ->whereNull($col('applied_at')),
            'starred' => $query
                ->whereNotNull($col('starred_at'))
                ->whereNull($col('shortlisted_at'))
                ->whereNull($col('applied_at')),
            'shortlisted' => $query
                ->whereNotNull($col('shortlisted_at'))
                ->whereNull($col('applied_at')),
            'applied' => $query->whereNotNull($col('applied_at')),
            default => $query,
        };
    }

    /**
     * Join a listings query to the user's best pivot per listing, plus that
     * pivot's target profile. Callers supply their own select list.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public static function joinBestPivot(Builder $query, int $userId): Builder
    {
        $bestPivotId = self::bestPivotIdSubquery($userId);

        return $query
            ->join('listing_user', function ($join) use ($userId, $bestPivotId) {
                // Interpolated rather than bound: bindings inside a join closure
                // are collected before the where bindings and would reorder.
                $join->on('listings.id', '=', 'listing_user.listing_id')
                    ->where('listing_user.user_id', $userId)
                    ->whereRaw('listing_user.id = ('.$bestPivotId->toRawSql().')');
            })
            ->leftJoin('target_profiles', 'listing_user.target_profile_id', '=', 'target_profiles.id');
    }

    /**
     * User-state flags are per (listing, user), but there is one pivot row per
     * target profile, so every flag has to be written across all of them.
     *
     * This goes through the query builder, so casts() never runs — pass scalars
     * (`$enum->value`, not the enum instance).
     *
     * @param  array<string, mixed>  $values
     */
    private function updateAllForListingUser(array $values): void
    {
        static::query()
            ->where('listing_id', $this->listing_id)
            ->where('user_id', $this->user_id)
            ->update($values);
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
            'applied_at' => 'datetime',
            'outcome' => ApplicationOutcome::class,
            'outcome_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'digested_at' => 'datetime',
        ];
    }
}
