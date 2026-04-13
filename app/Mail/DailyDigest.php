<?php

namespace App\Mail;

use App\Models\Application;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

class DailyDigest extends Mailable
{
    /**
     * @param  Collection<int, Listing>  $relevantListings
     * @param  Collection<int, Listing>  $maybeListings
     * @param  Collection<int, Application>  $readyApplications
     * @param  Collection<int, Application>  $failedApplications
     * @param  Collection<int, Listing>  $shortlistedWithoutApplications
     * @param  array{total_scraped: int, relevant_count: int, maybe_count: int, irrelevant_count: int, ai_total_cost: float, ai_usage_breakdown: array<int, array{model: string, cost: float, requests: int}>}  $stats
     */
    public function __construct(
        public readonly User $user,
        public readonly Collection $relevantListings,
        public readonly Collection $maybeListings,
        public readonly Collection $readyApplications,
        public readonly Collection $failedApplications,
        public readonly Collection $shortlistedWithoutApplications,
        public readonly array $stats,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Daily Job Digest — '.now()->format('M j, Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.daily-digest',
        );
    }
}
