<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class UserCapReached extends Mailable
{
    public function __construct(
        public readonly User $user,
        public readonly float $spend,
        public readonly float $cap,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "AI cap reached: {$this->user->email}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.user-cap-reached');
    }
}
