<?php

use App\ApplicationStatus;
use App\Mail\DailyDigest;
use App\Models\AiUsage;
use App\Models\Application;
use App\Models\Listing;
use App\Relevance;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    config(['profile.email' => 'test@example.com']);
});

it('sends the digest email', function () {
    Listing::factory()->scored(Relevance::Relevant)->create();

    $this->artisan('digest:send')
        ->assertSuccessful()
        ->expectsOutputToContain('Daily digest sent to test@example.com');

    Mail::assertSent(DailyDigest::class, function ($mail) {
        return $mail->hasTo('test@example.com');
    });
});

it('includes only listings scored in the last 24 hours', function () {
    $recent = Listing::factory()->scored(Relevance::Relevant)->create([
        'title' => 'Recent Job',
        'scored_at' => now()->subHours(2),
    ]);

    Listing::factory()->scored(Relevance::Relevant)->create([
        'title' => 'Old Job',
        'scored_at' => now()->subHours(48),
    ]);

    $this->artisan('digest:send')->assertSuccessful();

    Mail::assertSent(DailyDigest::class, function (DailyDigest $mail) use ($recent) {
        return $mail->relevantListings->count() === 1
            && $mail->relevantListings->first()->id === $recent->id;
    });
});

it('fails when no profile email is configured', function () {
    config(['profile.email' => null]);

    $this->artisan('digest:send')
        ->assertFailed()
        ->expectsOutputToContain('No profile email configured');

    Mail::assertNothingSent();
});

it('includes ready and failed application updates', function () {
    $readyApp = Application::factory()->ready()->create([
        'updated_at' => now()->subHours(1),
    ]);

    $failedApp = Application::factory()->state(['status' => ApplicationStatus::Failed])->create([
        'updated_at' => now()->subHours(1),
    ]);

    Application::factory()->ready()->create([
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
    $shortlisted = Listing::factory()->scored()->shortlisted()->create();

    $withApp = Listing::factory()->scored()->shortlisted()->create();
    Application::factory()->for($withApp)->create();

    $this->artisan('digest:send')->assertSuccessful();

    Mail::assertSent(DailyDigest::class, function (DailyDigest $mail) use ($shortlisted) {
        return $mail->shortlistedWithoutApplications->count() === 1
            && $mail->shortlistedWithoutApplications->first()->id === $shortlisted->id;
    });
});

it('calculates ai usage stats', function () {
    AiUsage::factory()->count(3)->create([
        'model' => 'anthropic/claude-haiku-4-5',
        'cost' => 0.50,
    ]);

    AiUsage::factory()->create([
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
