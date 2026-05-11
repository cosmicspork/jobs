<?php

use App\Services\Scrapers\WeWorkRemotelyScraper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function wwrRss(array $items): string
{
    $itemsXml = '';
    foreach ($items as $item) {
        $itemsXml .= '<item>'
            .'<title>'.($item['title'] ?? '').'</title>'
            .'<region>'.($item['region'] ?? 'Anywhere in the World').'</region>'
            .'<category>'.($item['category'] ?? 'Programming').'</category>'
            .'<description><![CDATA['.($item['description'] ?? '').']]></description>'
            .'<pubDate>'.($item['pubDate'] ?? 'Mon, 01 Apr 2026 00:00:00 +0000').'</pubDate>'
            .'<guid>'.($item['guid'] ?? '').'</guid>'
            .'<link>'.($item['link'] ?? '').'</link>'
            .'</item>';
    }

    return '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel>'.$itemsXml.'</channel></rss>';
}

/**
 * @param  array<string, array<int, array<string, string>>>  $perCategory  category-slug => items
 */
function fakeWwr(array $perCategory): void
{
    Http::fake(function (Request $request) use ($perCategory) {
        foreach ($perCategory as $slug => $items) {
            if (Str::contains($request->url(), "/categories/{$slug}.rss")) {
                return Http::response(wwrRss($items));
            }
        }

        // Default: respond 200 with empty channel so unhandled categories don't 500.
        return Http::response(wwrRss([]));
    });
}

it('aggregates items across multiple category feeds', function () {
    fakeWwr([
        'remote-back-end-programming-jobs' => [
            ['title' => 'Acme: Backend Engineer', 'guid' => 'wwr-1', 'link' => 'https://weworkremotely.com/remote-jobs/acme-be'],
        ],
        'remote-front-end-programming-jobs' => [
            ['title' => 'BigCo: Frontend Engineer', 'guid' => 'wwr-2', 'link' => 'https://weworkremotely.com/remote-jobs/bigco-fe'],
        ],
    ]);

    $listings = iterator_to_array((new WeWorkRemotelyScraper)->scrape(), preserve_keys: false);
    $titles = array_column($listings, 'title');

    expect($titles)->toContain('Backend Engineer')
        ->and($titles)->toContain('Frontend Engineer');
});

it('dedupes items appearing in multiple categories by guid', function () {
    fakeWwr([
        'remote-back-end-programming-jobs' => [
            ['title' => 'Acme: Engineer', 'guid' => 'wwr-shared', 'link' => 'https://weworkremotely.com/remote-jobs/acme'],
        ],
        'remote-full-stack-programming-jobs' => [
            ['title' => 'Acme: Engineer', 'guid' => 'wwr-shared', 'link' => 'https://weworkremotely.com/remote-jobs/acme'],
        ],
    ]);

    $listings = iterator_to_array((new WeWorkRemotelyScraper)->scrape(), preserve_keys: false);

    expect($listings)->toHaveCount(1);
});

it('splits the rss title on colon space into company and position', function () {
    fakeWwr([
        'remote-back-end-programming-jobs' => [
            ['title' => 'Consensys: Senior Product Manager', 'guid' => 'wwr-3', 'link' => 'https://weworkremotely.com/remote-jobs/consensys-spm'],
        ],
    ]);

    $listings = iterator_to_array((new WeWorkRemotelyScraper)->scrape(), preserve_keys: false);

    expect($listings[0]['company'])->toBe('Consensys')
        ->and($listings[0]['title'])->toBe('Senior Product Manager');
});

it('falls back to Unknown company when title lacks a colon', function () {
    fakeWwr([
        'remote-back-end-programming-jobs' => [
            ['title' => 'Backend Engineer Wanted', 'guid' => 'wwr-4', 'link' => 'https://weworkremotely.com/remote-jobs/anon-be'],
        ],
    ]);

    $listings = iterator_to_array((new WeWorkRemotelyScraper)->scrape(), preserve_keys: false);

    expect($listings[0]['company'])->toBe('Unknown')
        ->and($listings[0]['title'])->toBe('Backend Engineer Wanted');
});

it('regexes salary out of the description', function () {
    fakeWwr([
        'remote-back-end-programming-jobs' => [
            [
                'title' => 'Acme: Engineer',
                'guid' => 'wwr-5',
                'link' => 'https://weworkremotely.com/remote-jobs/acme-eng',
                'description' => '<p>We offer $110k - $160k plus equity.</p>',
            ],
        ],
    ]);

    $listings = iterator_to_array((new WeWorkRemotelyScraper)->scrape(), preserve_keys: false);

    expect($listings[0]['salary_min'])->toBe(110000)
        ->and($listings[0]['salary_max'])->toBe(160000);
});

it('sets source_url equal to url', function () {
    fakeWwr([
        'remote-back-end-programming-jobs' => [
            ['title' => 'Acme: Engineer', 'guid' => 'wwr-6', 'link' => 'https://weworkremotely.com/remote-jobs/acme-6'],
        ],
    ]);

    $listings = iterator_to_array((new WeWorkRemotelyScraper)->scrape(), preserve_keys: false);

    expect($listings[0]['url'])->toBe('https://weworkremotely.com/remote-jobs/acme-6')
        ->and($listings[0]['source_url'])->toBe($listings[0]['url']);
});

it('skips categories that fail to load', function () {
    Http::fake(function (Request $request) {
        if (Str::contains($request->url(), '/categories/remote-back-end-programming-jobs.rss')) {
            return Http::response(wwrRss([
                ['title' => 'Acme: Backend Engineer', 'guid' => 'wwr-7', 'link' => 'https://weworkremotely.com/remote-jobs/acme-be-7'],
            ]));
        }

        // Every other category 503s — should not throw or short-circuit.
        return Http::response('', 503);
    });

    $listings = iterator_to_array((new WeWorkRemotelyScraper)->scrape(), preserve_keys: false);

    expect($listings)->toHaveCount(1)
        ->and($listings[0]['title'])->toBe('Backend Engineer');
});
