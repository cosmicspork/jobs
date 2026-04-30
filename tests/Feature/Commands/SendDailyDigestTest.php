<?php

use App\ApplicationStatus;
use App\Mail\DailyDigest;
use App\Models\AiUsage;
use App\Models\Application;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use App\Relevance;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    // Disable digest for any seeded users so only our test user triggers sends
    User::query()->update(['digest_enabled' => false]);

    $this->user = User::factory()->ic()->create([
        'digest_enabled' => true,
        'timezone' => 'America/Chicago',
        'digest_time' => now()->timezone('America/Chicago')->format('H:i'),
    ]);
    $this->target = $this->user->targetProfiles()->first();
    $this->actingAs($this->user);
});

it('sends the digest email', function () {
    $listing = Listing::factory()->create();
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);

    $this->artisan('digest:send')->assertSuccessful();

    Mail::assertSent(DailyDigest::class, function ($mail) {
        return $mail->hasTo($this->user->email);
    });
});

it('skips users with incomplete profiles and logs a warning', function () {
    Log::spy();

    $bare = User::factory()->create([
        'digest_enabled' => true,
        'timezone' => 'America/Chicago',
        'digest_time' => now()->timezone('America/Chicago')->format('H:i'),
    ]);

    $this->artisan('digest:send')->assertSuccessful();

    Mail::assertNotSent(DailyDigest::class, fn (DailyDigest $mail) => $mail->hasTo($bare->email));
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context) => $message === 'Skipping daily digest for user with incomplete profile'
            && $context['user_id'] === $bare->id);
});

it('only sends to users whose digest_time matches the current time in their timezone', function () {
    $this->user->update(['digest_time' => now()->timezone('America/Chicago')->addHour()->format('H:i')]);

    $this->artisan('digest:send')->assertSuccessful();

    Mail::assertNothingSent();
});

it('includes only listings scored in the last 24 hours', function () {
    $recentListing = Listing::factory()->create(['title' => 'Recent Job']);
    ListingUser::create([
        'listing_id' => $recentListing->id,
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now()->subHours(2),
    ]);

    $oldListing = Listing::factory()->create(['title' => 'Old Job']);
    ListingUser::create([
        'listing_id' => $oldListing->id,
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now()->subHours(48),
    ]);

    $this->artisan('digest:send')->assertSuccessful();

    Mail::assertSent(DailyDigest::class, function (DailyDigest $mail) use ($recentListing) {
        return $mail->relevantListings->count() === 1
            && $mail->relevantListings->first()->id === $recentListing->id;
    });
});

it('includes ready and failed application updates', function () {
    $readyApp = Application::factory()->ready()->create([
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
        'updated_at' => now()->subHours(1),
    ]);

    $failedApp = Application::factory()->state(['status' => ApplicationStatus::Failed])->create([
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
        'updated_at' => now()->subHours(1),
    ]);

    Application::factory()->ready()->create([
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
        'updated_at' => now()->subHours(48),
    ]);

    $this->artisan('digest:send')->assertSuccessful();

    Mail::assertSent(DailyDigest::class, function (DailyDigest $mail) use ($readyApp, $failedApp) {
        return $mail->readyApplications->count() === 1
            && $mail->readyApplications->first()->id === $readyApp->id
            && $mail->failedApplications->count() === 1
            && $mail->failedApplications->first()->id === $failedApp->id;
    });
});

it('includes shortlisted listings without applications', function () {
    $shortlisted = Listing::factory()->create();
    ListingUser::create([
        'listing_id' => $shortlisted->id,
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
        'shortlisted_at' => now(),
    ]);

    $withApp = Listing::factory()->create();
    ListingUser::create([
        'listing_id' => $withApp->id,
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
        'shortlisted_at' => now(),
    ]);
    Application::factory()->for($withApp)->create([
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
    ]);

    $this->artisan('digest:send')->assertSuccessful();

    Mail::assertSent(DailyDigest::class, function (DailyDigest $mail) use ($shortlisted) {
        return $mail->shortlistedWithoutApplications->count() === 1
            && $mail->shortlistedWithoutApplications->first()->id === $shortlisted->id;
    });
});

it('calculates ai usage stats', function () {
    AiUsage::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'model' => 'anthropic/claude-haiku-4-5',
        'cost' => 0.50,
    ]);

    AiUsage::factory()->create([
        'user_id' => $this->user->id,
        'model' => 'anthropic/claude-haiku-4-5',
        'cost' => 0.25,
        'created_at' => now()->subDays(2),
    ]);

    $this->artisan('digest:send')->assertSuccessful();

    Mail::assertSent(DailyDigest::class, function (DailyDigest $mail) {
        return $mail->stats['ai_total_cost'] == 1.50
            && count($mail->stats['ai_usage_breakdown']) === 1
            && $mail->stats['ai_usage_breakdown'][0]['requests'] === 3;
    });
});
