<?php

use App\Models\Listing;

it('returns atom xml content type', function () {
    $this->get('/feed.xml')
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/atom+xml');
});

it('includes scored listings above threshold', function () {
    Listing::factory()->scored(85)->create(['title' => 'Senior Laravel Dev']);
    Listing::factory()->scored(50)->create(['title' => 'Junior PHP Dev']);
    Listing::factory()->create(['title' => 'Unscored Listing']);

    $response = $this->get('/feed.xml');

    $response->assertSuccessful()
        ->assertSee('Senior Laravel Dev')
        ->assertDontSee('Junior PHP Dev')
        ->assertDontSee('Unscored Listing');
});

it('excludes listings scored more than 7 days ago', function () {
    Listing::factory()->create([
        'title' => 'Stale Listing',
        'score' => 90,
        'score_data' => ['matched_skills' => [], 'gaps' => [], 'reasoning' => '', 'salary_match' => true],
        'scored_at' => now()->subDays(8),
    ]);

    $this->get('/feed.xml')
        ->assertSuccessful()
        ->assertDontSee('Stale Listing');
});

it('orders listings by score descending', function () {
    Listing::factory()->scored(90)->create(['title' => 'Best Match']);
    Listing::factory()->scored(75)->create(['title' => 'Good Match']);

    $response = $this->get('/feed.xml');
    $content = $response->getContent();

    expect(strpos($content, 'Best Match'))->toBeLessThan(strpos($content, 'Good Match'));
});

it('renders valid atom xml structure', function () {
    Listing::factory()->scored(80)->create();

    $response = $this->get('/feed.xml');
    $content = $response->getContent();

    expect($content)->toContain('<feed xmlns="http://www.w3.org/2005/Atom">')
        ->toContain('<entry>')
        ->toContain('</feed>');
});

it('returns empty feed when no listings match', function () {
    $response = $this->get('/feed.xml');

    $response->assertSuccessful();
    expect($response->getContent())->not->toContain('<entry>');
});
