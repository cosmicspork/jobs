<?php

use App\Filament\Pages\Home;
use App\Filament\Widgets\ListingStats;
use App\Filament\Widgets\ProfileCompletionChecklist;
use App\Mail\BoardRequested;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use App\Relevance;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    config(['scoring.admin_alert_email' => 'admin@example.com']);
});

it('renders the home page', function () {
    login();

    $this->get(route('filament.admin.pages.home'))
        ->assertSuccessful();
});

it('shows the completion checklist to users with an incomplete profile', function () {
    login(User::factory()->create());

    Livewire::test(ProfileCompletionChecklist::class)
        ->assertSee('Finish setting up your profile');
});

it('hides the completion checklist once the minimum profile is met', function () {
    login(User::factory()->ic()->create());

    expect(ProfileCompletionChecklist::canView())->toBeFalse();
});

it('displays listing stats', function () {
    $user = login();

    Listing::factory(3)->create()->each(fn (Listing $listing) => ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]));

    Listing::factory(2)->create()->each(fn (Listing $listing) => ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
    ]));

    Livewire::test(ListingStats::class)
        ->assertSee('Total Listings')
        ->assertSee('5')
        ->assertSee('Relevant')
        ->assertSee('Unscored');
});

it('sends the board request email and clears the form', function () {
    Mail::fake();
    login(User::factory()->ic()->create());

    Livewire::test(Home::class)
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
    login(User::factory()->ic()->create());

    Livewire::test(Home::class)
        ->set('data.name', 'Indeed')
        ->set('data.url', 'https://indeed.com')
        ->call('submit');

    Mail::assertNothingSent();
});
