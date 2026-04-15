<?php

use App\Filament\Pages\RequestBoard;
use App\Mail\BoardRequested;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    config(['scoring.admin_alert_email' => 'admin@example.com']);
    $this->user = login(User::factory()->ic()->create());
});

it('renders the request-a-board page', function () {
    $this->get(route('filament.admin.pages.request-board'))
        ->assertSuccessful();
});

it('sends the request email and clears the form', function () {
    Mail::fake();

    Livewire::test(RequestBoard::class)
        ->set('data.name', 'Indeed')
        ->set('data.url', 'https://indeed.com')
        ->set('data.notes', 'IT helpdesk roles, remote')
        ->call('submit')
        ->assertNotified();

    Mail::assertSent(BoardRequested::class, function ($mail) {
        return $mail->hasTo('admin@example.com')
            && $mail->boardName === 'Indeed'
            && $mail->boardUrl === 'https://indeed.com';
    });
});

it('warns when admin email is not configured', function () {
    Mail::fake();
    config(['scoring.admin_alert_email' => null]);

    Livewire::test(RequestBoard::class)
        ->set('data.name', 'Indeed')
        ->set('data.url', 'https://indeed.com')
        ->call('submit');

    Mail::assertNothingSent();
});
