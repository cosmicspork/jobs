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
use Illuminate\Support\Facades\Mail;

#[Signature('digest:send')]
#[Description('Send the daily job digest email')]
class SendDailyDigest extends Command
{
    public function handle(): int
    {
        $users = User::where('digest_enabled', true)->get();

        if ($users->isEmpty()) {
            $this->info('No users with digest enabled.');

            return self::SUCCESS;
        }

        $since = now()->subDay();
        $sent = 0;

        foreach ($users as $user) {
            $scoredPivots = ListingUser::query()
                ->where('user_id', $user->id)
                ->whereIn('relevance', [Relevance::Relevant->value, Relevance::Maybe->value, Relevance::Irrelevant->value])
                ->where('scored_at', '>=', $since)
                ->with('listing')
                ->latest('scored_at')
                ->get()
                ->groupBy('relevance');

            $relevantListings = $scoredPivots->get(Relevance::Relevant->value, collect())->map(function ($pivot) {
                $listing = $pivot->listing;
                $listing->setAttribute('score_data', $pivot->score_data);

                return $listing;
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
                ->with('listing')
                ->whereDoesntHave('listing.applications', fn ($q) => $q->where('user_id', $user->id))
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
                'ai_usage_breakdown' => $aiUsageBreakdown->map(
                    fn ($row) => [
                        'model' => AiUsage::shortModelName($row->model),
                        'cost' => (float) $row->total_cost,
                        'requests' => (int) $row->requests,
                    ]
                )->all(),
            ];

            Mail::to($user->email)->send(new DailyDigest(
                user: $user,
                relevantListings: $relevantListings,
                maybeListings: $maybeListings,
                readyApplications: $readyApplications,
                failedApplications: $failedApplications,
                shortlistedWithoutApplications: $shortlistedWithoutApplications,
                stats: $stats,
            ));

            $sent++;
        }

        $this->info("Daily digest sent to {$sent} user(s).");

        return self::SUCCESS;
    }
}
