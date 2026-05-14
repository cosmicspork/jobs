<?php

use App\Filament\Pages\Profile;
use App\Jobs\ExportUserData;
use App\Mail\UserDataExportReady;
use App\Models\AiUsage;
use App\Models\Application;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use App\Relevance;
use App\Services\UserDataExporter;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

it('queues the export job when the Profile action fires', function () {
    Queue::fake();
    $user = login(User::factory()->ic()->create());

    Livewire::test(Profile::class)
        ->callAction(TestAction::make('exportUserData'))
        ->assertNotified();

    Queue::assertPushed(ExportUserData::class, fn ($job) => $job->user->is($user));
});

it('builds a zip with manifest.json, README, and PDFs when the job runs', function () {
    Storage::fake();
    Mail::fake();

    $user = User::factory()->ic()->create();
    $listing = Listing::factory()->create(['title' => 'Senior Laravel Developer']);
    $target = $user->targetProfiles()->first();

    Storage::put('resumes/test-resume.pdf', 'fake-pdf-bytes');
    Storage::put('cover-letters/test-cover.pdf', 'fake-cover-bytes');

    Application::factory()
        ->ready()
        ->create([
            'user_id' => $user->id,
            'listing_id' => $listing->id,
            'target_profile_id' => $target->id,
            'resume_path' => 'resumes/test-resume.pdf',
            'cover_letter_path' => 'cover-letters/test-cover.pdf',
        ]);

    (new ExportUserData($user))->handle(app(UserDataExporter::class));

    $files = Storage::allFiles("exports/{$user->id}");
    expect($files)->toHaveCount(1);

    $zipPath = Storage::path($files[0]);
    $zip = new ZipArchive;
    expect($zip->open($zipPath))->toBeTrue();

    $manifestJson = $zip->getFromName('manifest.json');
    expect($manifestJson)->not->toBeFalse();
    $manifest = json_decode($manifestJson, true);

    expect($manifest)
        ->toHaveKey('schema_version', '1')
        ->toHaveKey('user')
        ->toHaveKey('applications')
        ->and($manifest['user'])->not->toHaveKey('password')
        ->and($manifest['user'])->not->toHaveKey('is_admin')
        ->and($manifest['user']['email'])->toBe($user->email);

    expect($zip->getFromName('README.txt'))->toContain('Schema version');
    expect($zip->getFromName('resumes/test-resume.pdf'))->toBe('fake-pdf-bytes');
    expect($zip->getFromName('cover-letters/test-cover.pdf'))->toBe('fake-cover-bytes');

    $zip->close();
});

it('excludes internal scoring state and reasoning tokens from the manifest', function () {
    $user = User::factory()->ic()->create();
    $target = $user->targetProfiles()->first();
    $listing = Listing::factory()->create();

    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $target->id,
        'relevance' => Relevance::Relevant,
        'score_data' => ['secret_signals' => 'do-not-export'],
        'scored_at' => now(),
    ]);

    AiUsage::factory()->create([
        'user_id' => $user->id,
        'reasoning_tokens' => 999,
    ]);

    $manifest = app(UserDataExporter::class)->export($user);

    expect(json_encode($manifest))->not->toContain('secret_signals');
    foreach ($manifest['ai_usages'] as $usage) {
        expect($usage)->not->toHaveKey('reasoning_tokens');
    }
});

it('sends the ready email with a 24h signed download URL', function () {
    Storage::fake();
    Mail::fake();

    $user = User::factory()->ic()->create();
    (new ExportUserData($user))->handle(app(UserDataExporter::class));

    Mail::assertSent(UserDataExportReady::class, function (UserDataExportReady $mail) use ($user) {
        return $mail->user->is($user)
            && str_contains($mail->signedUrl, '/account/data-export/'.$user->id.'/')
            && str_contains($mail->signedUrl, 'signature=');
    });
});

it('rejects unsigned requests to the download route', function () {
    $user = login(User::factory()->create());

    $this->get("/account/data-export/{$user->id}/anything.zip")->assertForbidden();
});

it('rejects a signed URL for a different user', function () {
    Storage::fake();
    $alice = User::factory()->create();
    $bob = login(User::factory()->create());

    Storage::put("exports/{$alice->id}/abc.zip", 'fake');
    $signedUrl = URL::temporarySignedRoute(
        'user-data.download',
        now()->addHours(24),
        ['user' => $alice->id, 'file' => 'abc.zip'],
    );

    $this->get($signedUrl)->assertForbidden();
});

it('serves the ZIP to the owning user with a valid signature', function () {
    Storage::fake();
    $user = login(User::factory()->create());

    Storage::put("exports/{$user->id}/abc.zip", 'fake-zip-bytes');
    $signedUrl = URL::temporarySignedRoute(
        'user-data.download',
        now()->addHours(24),
        ['user' => $user->id, 'file' => 'abc.zip'],
    );

    $response = $this->get($signedUrl);
    $response->assertOk();
    expect($response->streamedContent())->toBe('fake-zip-bytes');
});

it('prunes exports older than the cutoff', function () {
    Storage::fake();
    Storage::put('exports/1/old.zip', 'x');
    Storage::put('exports/1/new.zip', 'x');
    touch(Storage::path('exports/1/old.zip'), now()->subDays(30)->getTimestamp());
    touch(Storage::path('exports/1/new.zip'), now()->subHours(2)->getTimestamp());

    $this->artisan('exports:prune')->assertSuccessful();

    expect(Storage::exists('exports/1/old.zip'))->toBeFalse();
    expect(Storage::exists('exports/1/new.zip'))->toBeTrue();
});
