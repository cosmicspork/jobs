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

        $relevantListings = Listing::query()
            ->where('relevance', Relevance::Relevant)
            ->where('scored_at', '>=', $since)
            ->latest('scored_at')
            ->get();

        $maybeListings = Listing::query()
            ->where('relevance', Relevance::Maybe)
            ->where('scored_at', '>=', $since)
            ->latest('scored_at')
            ->get();

        $readyApplications = Application::query()
            ->with('listing')
            ->where('status', ApplicationStatus::Ready)
            ->where('updated_at', '>=', $since)
            ->get();

        $failedApplications = Application::query()
            ->with('listing')
            ->where('status', ApplicationStatus::Failed)
            ->where('updated_at', '>=', $since)
            ->get();

        $shortlistedWithoutApplications = Listing::query()
            ->whereNotNull('shortlisted_at')
            ->whereDoesntHave('applications')
            ->get();

        $irrelevantCount = Listing::query()
            ->where('relevance', Relevance::Irrelevant)
            ->where('scored_at', '>=', $since)
            ->count();

        $totalScraped = Listing::query()
            ->where('scraped_at', '>=', $since)
            ->count();

        $aiUsage = AiUsage::query()
            ->where('created_at', '>=', $since)
            ->get();

        $stats = [
            'total_scraped' => $totalScraped,
            'relevant_count' => $relevantListings->count(),
            'maybe_count' => $maybeListings->count(),
            'irrelevant_count' => $irrelevantCount,
            'ai_total_cost' => $aiUsage->sum('cost'),
            'ai_usage_breakdown' => $aiUsage->groupBy('model')->map(
                fn ($group) => [
                    'model' => AiUsage::shortModelName($group->first()->model),
                    'cost' => $group->sum('cost'),
                    'requests' => $group->count(),
                ]
            )->values()->all(),
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
