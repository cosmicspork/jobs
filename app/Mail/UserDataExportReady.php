<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class UserDataExportReady extends Mailable
{
    public function __construct(
        public readonly User $user,
        public readonly string $signedUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your data export is ready',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.user-data-export-ready');
    }
}
