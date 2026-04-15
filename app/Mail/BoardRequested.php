<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BoardRequested extends Mailable
{
    public function __construct(
        public readonly User $user,
        public readonly string $boardName,
        public readonly string $boardUrl,
        public readonly ?string $notes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Board request: {$this->boardName}",
            replyTo: [new Address($this->user->email, $this->user->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.board-requested');
    }
}
