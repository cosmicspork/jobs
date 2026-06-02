<?php

use App\Mail\DailyDigest;
use App\Models\TargetProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Mail::fake());

afterEach(fn () => Carbon::setTestNow());

/**
 * A user who passes hasMinimumProfile() and has digests enabled.
 *
 * @param  array<string, mixed>  $attributes
 */
function eligibleDigestUser(array $attributes = []): User
{
    $user = User::factory()->manager()->create(array_merge([
        'digest_enabled' => true,
        'digest_time' => '08:00',
        'timezone' => 'UTC',
    ], $attributes));

    TargetProfile::factory()->for($user)->create();

    return $user;
}

it('sends at the first scheduler run at or after digest_time', function () {
    $user = eligibleDigestUser(['digest_time' => '08:00']);
    Carbon::setTestNow('2026-06-02 08:00:00');

    $this->artisan('digest:send')->assertSuccessful();

    Mail::assertSent(DailyDigest::class, fn (DailyDigest $mail) => $mail->hasTo($user->email));
    expect($user->refresh()->daily_digest_sent_on?->toDateString())->toBe('2026-06-02');
});

it('is tolerant of a coarse 15-minute scheduler cadence', function () {
    // 08:07 never lands on a :00/:15/:30/:45 wake; the at-or-after window still
    // delivers at the 08:15 run. This is the exact-match regression the fix closes.
    eligibleDigestUser(['digest_time' => '08:07']);
    Carbon::setTestNow('2026-06-02 08:15:00');

    $this->artisan('digest:send')->assertSuccessful();

    Mail::assertSent(DailyDigest::class);
});

it('does not send before digest_time', function () {
    eligibleDigestUser(['digest_time' => '08:00']);
    Carbon::setTestNow('2026-06-02 07:45:00');

    $this->artisan('digest:send')->assertSuccessful();

    Mail::assertNothingSent();
});

it('sends only once per local day even across multiple runs', function () {
    eligibleDigestUser(['digest_time' => '08:00']);

    Carbon::setTestNow('2026-06-02 08:00:00');
    $this->artisan('digest:send')->assertSuccessful();

    Carbon::setTestNow('2026-06-02 08:30:00');
    $this->artisan('digest:send')->assertSuccessful();

    Mail::assertSent(DailyDigest::class, 1);
});

it('sends again the next day', function () {
    eligibleDigestUser(['digest_time' => '08:00']);

    Carbon::setTestNow('2026-06-02 08:00:00');
    $this->artisan('digest:send')->assertSuccessful();

    Carbon::setTestNow('2026-06-03 08:05:00');
    $this->artisan('digest:send')->assertSuccessful();

    Mail::assertSent(DailyDigest::class, 2);
});

it('evaluates digest_time in the user timezone', function () {
    // 08:00 America/Chicago (CDT, UTC-5 in June) == 13:00 UTC.
    eligibleDigestUser(['digest_time' => '08:00', 'timezone' => 'America/Chicago']);

    Carbon::setTestNow('2026-06-02 12:30:00'); // 07:30 local — too early
    $this->artisan('digest:send')->assertSuccessful();
    Mail::assertNothingSent();

    Carbon::setTestNow('2026-06-02 13:05:00'); // 08:05 local — due
    $this->artisan('digest:send')->assertSuccessful();
    Mail::assertSent(DailyDigest::class);
});
