<?php

namespace App\Mail;

use App\Models\Application;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class DailyDigest extends Mailable
{
    /**
     * @param  Collection<int, Listing>  $relevantListings
     * @param  Collection<int, Listing>  $maybeListings
     * @param  Collection<int, Application>  $readyApplications
     * @param  Collection<int, Application>  $failedApplications
     * @param  Collection<int, Listing>  $shortlistedWithoutApplications
     * @param  array{screened_24h: int, screened_7d: int, relevant_7d: int, maybe_7d: int}  $stats
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
            with: [
                'unsubscribeUrl' => URL::signedRoute('digest.unsubscribe', ['user' => $this->user->id]),
            ],
        );
    }
}
