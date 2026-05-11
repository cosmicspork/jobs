<?php

use App\Services\Scrapers\RemoteOkScraper;
use Illuminate\Support\Facades\Http;

function fakeRemoteOk(array $items): void
{
    Http::fake([
        'remoteok.com/api' => Http::response($items),
    ]);
}

it('skips the legal header row at index 0', function () {
    fakeRemoteOk([
        ['last_updated' => 0, 'legal' => 'Link back, please.'],
        [
            'id' => '100',
            'position' => 'Senior Engineer',
            'company' => 'Acme',
            'url' => 'https://remoteok.com/remote-jobs/100',
            'apply_url' => 'https://acme.example/apply',
            'description' => 'Build cool things.',
        ],
    ]);

    $listings = iterator_to_array((new RemoteOkScraper)->scrape());

    expect($listings)->toHaveCount(1)
        ->and($listings[0]['company'])->toBe('Acme');
});

it('parses position, company, salary_min and salary_max into listings', function () {
    fakeRemoteOk([
        ['legal' => '...'],
        [
            'id' => '200',
            'position' => 'Backend Engineer',
            'company' => 'BigCo',
            'url' => 'https://remoteok.com/remote-jobs/200',
            'apply_url' => 'https://bigco.example/jobs/200',
            'description' => 'Work on infra.',
            'salary_min' => 140000,
            'salary_max' => 190000,
            'tags' => ['python', 'aws'],
            'location' => 'Worldwide',
        ],
    ]);

    $listings = iterator_to_array((new RemoteOkScraper)->scrape());

    expect($listings[0]['title'])->toBe('Backend Engineer')
        ->and($listings[0]['company'])->toBe('BigCo')
        ->and($listings[0]['salary_min'])->toBe(140000)
        ->and($listings[0]['salary_max'])->toBe(190000)
        ->and($listings[0]['remote'])->toBeTrue()
        ->and($listings[0]['raw_data']['tags'])->toBe(['python', 'aws'])
        ->and($listings[0]['raw_data']['location'])->toBe('Worldwide');
});

it('uses apply_url as url and the slug as source_url', function () {
    fakeRemoteOk([
        ['legal' => '...'],
        [
            'id' => '300',
            'position' => 'Engineer',
            'company' => 'Acme',
            'url' => 'https://remoteok.com/remote-jobs/300',
            'apply_url' => 'https://acme.example/apply/300',
            'description' => '',
        ],
    ]);

    $listings = iterator_to_array((new RemoteOkScraper)->scrape());

    expect($listings[0]['url'])->toBe('https://acme.example/apply/300')
        ->and($listings[0]['source_url'])->toBe('https://remoteok.com/remote-jobs/300');
});

it('falls back to slug when apply_url is missing', function () {
    fakeRemoteOk([
        ['legal' => '...'],
        [
            'id' => '400',
            'position' => 'Engineer',
            'company' => 'Acme',
            'url' => 'https://remoteok.com/remote-jobs/400',
            'description' => '',
        ],
    ]);

    $listings = iterator_to_array((new RemoteOkScraper)->scrape());

    expect($listings[0]['url'])->toBe('https://remoteok.com/remote-jobs/400')
        ->and($listings[0]['source_url'])->toBe('https://remoteok.com/remote-jobs/400');
});

it('parses salary from description when salary_min and salary_max are zero', function () {
    fakeRemoteOk([
        ['legal' => '...'],
        [
            'id' => '500',
            'position' => 'Engineer',
            'company' => 'Acme',
            'url' => 'https://remoteok.com/remote-jobs/500',
            'apply_url' => 'https://acme.example/apply',
            'description' => 'Compensation: $130k - $170k depending on experience.',
            'salary_min' => 0,
            'salary_max' => 0,
        ],
    ]);

    $listings = iterator_to_array((new RemoteOkScraper)->scrape());

    expect($listings[0]['salary_min'])->toBe(130000)
        ->and($listings[0]['salary_max'])->toBe(170000);
});

it('preserves mailto apply_url', function () {
    fakeRemoteOk([
        ['legal' => '...'],
        [
            'id' => '600',
            'position' => 'Engineer',
            'company' => 'Acme',
            'url' => 'https://remoteok.com/remote-jobs/600',
            'apply_url' => 'mailto:jobs@acme.example',
            'description' => '',
        ],
    ]);

    $listings = iterator_to_array((new RemoteOkScraper)->scrape());

    expect($listings[0]['url'])->toBe('mailto:jobs@acme.example');
});

it('returns empty on failed http', function () {
    Http::fake([
        'remoteok.com/api' => Http::response('', 500),
    ]);

    expect(iterator_to_array((new RemoteOkScraper)->scrape()))->toBeEmpty();
});
