<?php

use App\ApplicationQuestionSetStatus;
use App\Models\ApplicationQuestion;
use App\Models\ApplicationQuestionSet;
use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    login();
});

it('uses ulids for primary key', function () {
    $set = ApplicationQuestionSet::factory()->create();

    expect($set->id)->toBeString()->toHaveLength(26);
});

it('belongs to a listing optionally', function () {
    $withListing = ApplicationQuestionSet::factory()->create();
    $withoutListing = ApplicationQuestionSet::factory()->withoutListing()->create();

    expect($withListing->listing)->toBeInstanceOf(Listing::class)
        ->and($withoutListing->listing)->toBeNull();
});

it('has many questions', function () {
    $set = ApplicationQuestionSet::factory()->create();
    ApplicationQuestion::factory()->count(3)->create(['question_set_id' => $set->id]);

    expect($set->questions)->toHaveCount(3);
});

it('casts status to enum', function () {
    $set = ApplicationQuestionSet::factory()->create();

    expect($set->status)->toBe(ApplicationQuestionSetStatus::Draft);
});

it('cascades delete from listing', function () {
    $listing = Listing::factory()->create();
    $set = ApplicationQuestionSet::factory()->create(['listing_id' => $listing->id]);
    ApplicationQuestion::factory()->create(['question_set_id' => $set->id]);

    $listing->delete();

    expect(ApplicationQuestionSet::count())->toBe(0)
        ->and(ApplicationQuestion::count())->toBe(0);
});

it('tracks has been reviewed on questions', function () {
    $unreviewed = ApplicationQuestion::factory()->create();
    $reviewed = ApplicationQuestion::factory()->reviewed()->create();

    expect($unreviewed->hasBeenReviewed())->toBeFalse()
        ->and($reviewed->hasBeenReviewed())->toBeTrue();
});
