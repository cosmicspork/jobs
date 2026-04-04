<?php

namespace App\Console\Commands;

use App\ApplicationStatus;
use App\Mail\DailyDigest;
use App\Models\AiUsage;
use App\Models\Application;
use App\Models\Listing;
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
        $recipient = config('profile.email');

        if (! $recipient) {
            $this->error('No profile email configured. Set PROFILE_EMAIL in .env.');

            return self::FAILURE;
        }

        $since = now()->subDay();

        $scoredListings = Listing::query()
            ->whereIn('relevance', [Relevance::Relevant, Relevance::Maybe, Relevance::Irrelevant])
            ->where('scored_at', '>=', $since)
            ->latest('scored_at')
            ->get()
            ->groupBy('relevance');

        $relevantListings = $scoredListings->get(Relevance::Relevant->value, collect());
        $maybeListings = $scoredListings->get(Relevance::Maybe->value, collect());
        $irrelevantCount = $scoredListings->get(Relevance::Irrelevant->value, collect())->count();

        $applicationUpdates = Application::query()
            ->with('listing')
            ->whereIn('status', [ApplicationStatus::Ready, ApplicationStatus::Failed])
            ->where('updated_at', '>=', $since)
            ->get()
            ->groupBy('status');

        $readyApplications = $applicationUpdates->get(ApplicationStatus::Ready->value, collect());
        $failedApplications = $applicationUpdates->get(ApplicationStatus::Failed->value, collect());

        $shortlistedWithoutApplications = Listing::query()
            ->shortlistedWithoutApplications()
            ->get();

        $totalScraped = Listing::query()
            ->where('scraped_at', '>=', $since)
            ->count();

        $aiUsageBreakdown = AiUsage::query()
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

        Mail::to($recipient)->send(new DailyDigest(
            relevantListings: $relevantListings,
            maybeListings: $maybeListings,
            readyApplications: $readyApplications,
            failedApplications: $failedApplications,
            shortlistedWithoutApplications: $shortlistedWithoutApplications,
            stats: $stats,
        ));

        $this->info('Daily digest sent to '.$recipient);

        return self::SUCCESS;
    }
}
