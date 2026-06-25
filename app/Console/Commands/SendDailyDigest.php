<?php

namespace App\Console\Commands;

use App\ApplicationStatus;
use App\Mail\DailyDigest;
use App\Models\Application;
use App\Models\ListingUser;
use App\Models\User;
use App\Relevance;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

#[Signature('digest:send')]
#[Description('Send the daily job digest email')]
class SendDailyDigest extends Command
{
    public function handle(): int
    {
        $since = now()->subDay();
        $sent = 0;

        User::where('digest_enabled', true)
            ->cursor()
            ->filter($this->isDueNow(...))
            ->each(function (User $user) use ($since, &$sent) {
                if (! $user->hasMinimumProfile()) {
                    Log::warning('Skipping daily digest for user with incomplete profile', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                    ]);

                    return;
                }

                [$digest, $deliveredPivotIds] = $this->buildDigest($user, $since);
                Mail::to($user->email)->send($digest);

                if ($deliveredPivotIds !== []) {
                    ListingUser::query()
                        ->whereIn('id', $deliveredPivotIds)
                        ->update(['digested_at' => now()]);
                }

                $user->daily_digest_sent_on = now()->timezone($user->timezone)->startOfDay();
                $user->save();

                $sent++;
            });

        $this->info("Daily digest sent to {$sent} user(s).");

        return self::SUCCESS;
    }

    /**
     * Whether the user is due for today's digest. True once their local
     * digest_time has arrived and no digest has been sent on their local date
     * yet. This is an at-or-after check rather than an exact-minute match, so a
     * single coarse schedule:run — e.g. every 15 minutes on a hibernating
     * instance — still delivers exactly one digest per day, at the first run at
     * or after digest_time (and self-heals if a wake is missed).
     */
    protected function isDueNow(User $user): bool
    {
        $nowInTz = now()->timezone($user->timezone);
        $target = $nowInTz->copy()->setTimeFromTimeString(substr((string) $user->digest_time, 0, 5));

        if ($nowInTz->lessThan($target)) {
            return false;
        }

        return $user->daily_digest_sent_on?->toDateString() !== $nowInTz->toDateString();
    }

    /**
     * @return array{0: DailyDigest, 1: array<int, string>}
     */
    protected function buildDigest(User $user, Carbon $since): array
    {
        // Pivots scored in the past day, never previously surfaced in a digest.
        // Multiple per listing (one per active target); dedupe to the best-
        // relevance pivot per listing for display.
        $scoredPivots = ListingUser::query()
            ->where('user_id', $user->id)
            ->whereNotNull('relevance')
            ->whereNull('dismissed_at')
            ->whereNull('digested_at')
            ->where('scored_at', '>=', $since)
            ->orderByRaw(ListingUser::orderByRelevanceSql())
            ->orderByDesc('scored_at')
            ->with(['listing', 'targetProfile'])
            ->get()
            ->unique('listing_id')
            ->values()
            ->groupBy(fn (ListingUser $p) => $p->relevance->value);

        $attachContext = function (ListingUser $pivot) {
            $pivot->listing->setAttribute('score_data', $pivot->score_data);
            $pivot->listing->setAttribute('target_name', $pivot->targetProfile?->name);

            return $pivot->listing;
        };

        // Only "relevant" listings reach the inbox, ranked by fit_score (old
        // rows without one sink to the bottom), then recency, and capped to a
        // handful of high-quality matches. "maybe" pivots are still stamped as
        // digested below so they don't resurface, but are excluded from the email.
        $cap = (int) config('scoring.digest_relevant_cap', 10);

        $relevantPivots = $scoredPivots->get(Relevance::Relevant->value, collect())
            ->sortByDesc(fn (ListingUser $p) => [
                $p->score_data['fit_score'] ?? -1,
                $p->scored_at?->getTimestamp() ?? 0,
            ])
            ->take($cap)
            ->values();
        $maybePivots = $scoredPivots->get(Relevance::Maybe->value, collect());
        $relevantListings = $relevantPivots->map($attachContext);
        $maybeListings = collect();

        $applicationUpdates = Application::query()
            ->where('user_id', $user->id)
            ->with('listing')
            ->whereIn('status', [ApplicationStatus::Ready, ApplicationStatus::Failed])
            ->where('updated_at', '>=', $since)
            ->get()
            ->groupBy('status');

        $readyApplications = $applicationUpdates->get(ApplicationStatus::Ready->value, collect());
        $failedApplications = $applicationUpdates->get(ApplicationStatus::Failed->value, collect());

        $shortlistedPivots = ListingUser::query()
            ->where('user_id', $user->id)
            ->whereNotNull('shortlisted_at')
            ->whereNull('dismissed_at')
            ->whereNull('digested_at')
            ->where('shortlisted_at', '>=', $since)
            ->with('listing')
            ->whereDoesntHave('listing.applications', fn ($q) => $q->where('user_id', $user->id))
            ->latest('shortlisted_at')
            ->get()
            ->unique('listing_id')
            ->values();

        $shortlistedWithoutApplications = $shortlistedPivots->pluck('listing');

        $deliveredPivotIds = $relevantPivots
            ->concat($maybePivots)
            ->concat($shortlistedPivots)
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        $stats = [
            'screened_24h' => $this->countScreened($user, $since),
            'screened_7d' => $this->countScreened($user, now()->subDays(7)),
            'relevant_7d' => $this->countScoredRelevance($user, Relevance::Relevant, now()->subDays(7)),
            'maybe_7d' => $this->countScoredRelevance($user, Relevance::Maybe, now()->subDays(7)),
        ];

        return [
            new DailyDigest(
                user: $user,
                relevantListings: $relevantListings,
                maybeListings: $maybeListings,
                readyApplications: $readyApplications,
                failedApplications: $failedApplications,
                shortlistedWithoutApplications: $shortlistedWithoutApplications,
                stats: $stats,
            ),
            $deliveredPivotIds,
        ];
    }

    protected function countScreened(User $user, Carbon $since): int
    {
        return ListingUser::query()
            ->where('user_id', $user->id)
            ->whereNull('dismissed_at')
            ->where('created_at', '>=', $since)
            ->distinct('listing_id')
            ->count('listing_id');
    }

    protected function countScoredRelevance(User $user, Relevance $relevance, Carbon $since): int
    {
        return ListingUser::query()
            ->where('user_id', $user->id)
            ->whereNull('dismissed_at')
            ->where('relevance', $relevance)
            ->where('scored_at', '>=', $since)
            ->distinct('listing_id')
            ->count('listing_id');
    }
}
