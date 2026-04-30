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

                Mail::to($user->email)->send($this->buildDigest($user, $since));
                $sent++;
            });

        $this->info("Daily digest sent to {$sent} user(s).");

        return self::SUCCESS;
    }

    protected function isDueNow(User $user): bool
    {
        $nowInTz = now()->timezone($user->timezone)->format('H:i');
        $target = substr((string) $user->digest_time, 0, 5);

        return $nowInTz === $target;
    }

    protected function buildDigest(User $user, Carbon $since): DailyDigest
    {
        // Pivots scored in the past day — multiple per listing (one per active target).
        // Dedupe to the best-relevance pivot per listing for digest display.
        $scoredPivots = ListingUser::query()
            ->where('user_id', $user->id)
            ->whereNotNull('relevance')
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

        $relevantListings = $scoredPivots->get(Relevance::Relevant->value, collect())->map($attachContext);
        $maybeListings = $scoredPivots->get(Relevance::Maybe->value, collect())->map($attachContext);

        $applicationUpdates = Application::query()
            ->where('user_id', $user->id)
            ->with('listing')
            ->whereIn('status', [ApplicationStatus::Ready, ApplicationStatus::Failed])
            ->where('updated_at', '>=', $since)
            ->get()
            ->groupBy('status');

        $readyApplications = $applicationUpdates->get(ApplicationStatus::Ready->value, collect());
        $failedApplications = $applicationUpdates->get(ApplicationStatus::Failed->value, collect());

        $shortlistedWithoutApplications = ListingUser::query()
            ->where('user_id', $user->id)
            ->whereNotNull('shortlisted_at')
            ->where('shortlisted_at', '>=', $since)
            ->with('listing')
            ->whereDoesntHave('listing.applications', fn ($q) => $q->where('user_id', $user->id))
            ->latest('shortlisted_at')
            ->get()
            ->pluck('listing');

        $stats = [
            'screened_24h' => $this->countScreened($user, $since),
            'screened_7d' => $this->countScreened($user, now()->subDays(7)),
            'relevant_7d' => $this->countScoredRelevance($user, Relevance::Relevant, now()->subDays(7)),
            'maybe_7d' => $this->countScoredRelevance($user, Relevance::Maybe, now()->subDays(7)),
        ];

        return new DailyDigest(
            user: $user,
            relevantListings: $relevantListings,
            maybeListings: $maybeListings,
            readyApplications: $readyApplications,
            failedApplications: $failedApplications,
            shortlistedWithoutApplications: $shortlistedWithoutApplications,
            stats: $stats,
        );
    }

    protected function countScreened(User $user, Carbon $since): int
    {
        return ListingUser::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->distinct('listing_id')
            ->count('listing_id');
    }

    protected function countScoredRelevance(User $user, Relevance $relevance, Carbon $since): int
    {
        return ListingUser::query()
            ->where('user_id', $user->id)
            ->where('relevance', $relevance)
            ->where('scored_at', '>=', $since)
            ->distinct('listing_id')
            ->count('listing_id');
    }
}
