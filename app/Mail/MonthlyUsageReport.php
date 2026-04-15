<?php

namespace App\Mail;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MonthlyUsageReport extends Mailable
{
    /**
     * @param  array{ai_cost: float, listings_received: int, relevant: int, maybe: int, irrelevant: int, applications: int}  $stats
     */
    public function __construct(
        public readonly User $user,
        public readonly CarbonInterface $monthStart,
        public readonly array $stats,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your '.$this->monthStart->format('F Y').' usage report',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.monthly-usage-report');
    }
}
