<?php

use App\Models\Application;
use App\Models\Listing;
use App\Models\TargetProfile;
use App\Models\User;
use App\Services\ProfileExporter;
use App\Services\ProfileImporter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

it('exports a complete profile JSON shape', function () {
    $user = User::factory()->ic()->create();

    $payload = app(ProfileExporter::class)->export($user);

    expect($payload)
        ->toHaveKey('schema_version', '1')
        ->toHaveKey('exported_at')
        ->toHaveKey('account.name', $user->name)
        ->toHaveKey('account.email', $user->email)
        ->toHaveKey('profile.summary', $user->summary)
        ->toHaveKey('profile.skills')
        ->toHaveKey('profile.experience')
        ->toHaveKey('profile.timezone')
        ->and($payload['target_profiles'])->not->toBeEmpty()
        ->and($payload['target_profiles'][0])->toHaveKey('key')
        ->and($payload['target_profiles'][0]['key'])->toBe(Str::slug($payload['target_profiles'][0]['name']));
});

it('round-trips a profile from one user into a fresh user', function () {
    $source = User::factory()->ic()->create([
        'prompts' => ['scorer' => 'be terse'],
        'preferences' => ['theme' => 'dark'],
    ]);
    $payload = app(ProfileExporter::class)->export($source);

    $destination = User::factory()->create();
    expect($destination->targetProfiles()->count())->toBe(0);

    app(ProfileImporter::class)->import($destination->fresh(), $payload);
    $destination->refresh();

    expect($destination->summary)->toBe($source->summary)
        ->and($destination->skills)->toBe($source->skills)
        ->and($destination->experience)->toBe($source->experience)
        ->and($destination->education)->toBe($source->education)
        ->and($destination->prompts)->toBe(['scorer' => 'be terse'])
        ->and($destination->preferences)->toBe(['theme' => 'dark'])
        ->and($destination->targetProfiles()->count())->toBe($source->targetProfiles()->count());

    expect($destination->name)->not->toBe($source->name);
});

it('upserts target_profiles by name slug and preserves linked applications', function () {
    $user = User::factory()->ic()->create();
    $target = $user->targetProfiles()->first();
    $listing = Listing::factory()->create();

    $application = Application::factory()->ready()->create([
        'user_id' => $user->id,
        'listing_id' => $listing->id,
        'target_profile_id' => $target->id,
    ]);

    $payload = app(ProfileExporter::class)->export($user);
    $payload['target_profiles'][0]['positioning'] = 'A brand new positioning statement.';

    app(ProfileImporter::class)->import($user, $payload);

    expect(Application::find($application->id))->not->toBeNull();
    expect($user->fresh()->targetProfiles()->first()->positioning)->toBe('A brand new positioning statement.');
    expect($user->fresh()->targetProfiles()->first()->id)->toBe($target->id);
});

it('deactivates targets that are missing from the imported file', function () {
    $user = User::factory()->ic()->create();
    $keptTarget = $user->targetProfiles()->first();
    $extraTarget = TargetProfile::factory()->for($user)->manager()->create(['is_active' => true]);

    $payload = app(ProfileExporter::class)->export($user);
    $payload['target_profiles'] = array_values(array_filter(
        $payload['target_profiles'],
        fn ($t) => $t['key'] !== Str::slug($extraTarget->name),
    ));

    app(ProfileImporter::class)->import($user, $payload);

    expect($extraTarget->fresh()->is_active)->toBeFalse();
    expect($extraTarget->fresh()->exists)->toBeTrue();
    expect($keptTarget->fresh()->is_active)->toBeTrue();
});

it('rejects an import with an unsupported schema version', function () {
    $user = User::factory()->ic()->create();
    $payload = app(ProfileExporter::class)->export($user);
    $payload['schema_version'] = '99';

    expect(fn () => app(ProfileImporter::class)->import($user, $payload))
        ->toThrow(ValidationException::class);
});

it('rejects an import with malformed target_profiles', function () {
    $user = User::factory()->ic()->create();

    expect(fn () => app(ProfileImporter::class)->import($user, [
        'schema_version' => '1',
        'target_profiles' => [['name' => '']],
    ]))->toThrow(ValidationException::class);
});

it('reports import preview counts without writing', function () {
    $user = User::factory()->ic()->create();
    $payload = app(ProfileExporter::class)->export($user);

    $payload['target_profiles'][] = [
        'key' => 'brand-new-target',
        'name' => 'Brand New Target',
        'positioning' => 'New direction.',
        'target_titles' => ['Director'],
        'is_active' => true,
    ];

    $preview = app(ProfileImporter::class)->preview($user, $payload);

    expect($preview['added'])->toBe(1)
        ->and($preview['updated'])->toBe(1)
        ->and($preview['deactivated'])->toBe(0);

    expect($user->fresh()->targetProfiles()->count())->toBe(1);
});
