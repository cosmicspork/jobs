<?php

namespace App\Console\Commands;

use App\ApplicationStatus;
use App\Mail\DailyDigest;
use App\Models\AiUsage;
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
        $scoredPivots = ListingUser::query()
            ->where('user_id', $user->id)
            ->whereIn('relevance', [Relevance::Relevant->value, Relevance::Maybe->value, Relevance::Irrelevant->value])
            ->where('scored_at', '>=', $since)
            ->with('listing')
            ->latest('scored_at')
            ->get()
            ->groupBy('relevance');

        $relevantListings = $scoredPivots->get(Relevance::Relevant->value, collect())->map(function ($pivot) {
            $pivot->listing->setAttribute('score_data', $pivot->score_data);

            return $pivot->listing;
        });
        $maybeListings = $scoredPivots->get(Relevance::Maybe->value, collect())->pluck('listing');
        $irrelevantCount = $scoredPivots->get(Relevance::Irrelevant->value, collect())->count();

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

        $totalScraped = ListingUser::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->count();

        $aiUsageBreakdown = AiUsage::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->selectRaw('model, SUM(cost) as total_cost, COUNT(*) as requests')
            ->groupBy('model')
            ->get();

        $stats = [
            'total_scraped' => $totalScraped,
            'relevant_count' => $relevantListings->count(),
            'maybe_count' => $maybeListings->count(),
            'irrelevant_count' => $irrelevantCount,
            'ai_total_cost' => $aiUsageBreakdown->sum('total_cost'),
            'ai_usage_breakdown' => $aiUsageBreakdown->map(fn ($row) => [
                'model' => AiUsage::shortModelName($row->model),
                'cost' => (float) $row->total_cost,
                'requests' => (int) $row->requests,
            ])->all(),
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
}
