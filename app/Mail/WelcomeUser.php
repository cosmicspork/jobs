<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WelcomeUser extends Mailable
{
    public function __construct(public readonly User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.welcome-user',
            with: [
                'loginUrl' => url('/'),
                'forgotPasswordUrl' => route('filament.admin.auth.password-reset.request'),
            ],
        );
    }
}
