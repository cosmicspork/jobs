<?php

use App\Services\Scrapers\LarajobsScraper;
use Illuminate\Support\Facades\Http;

it('parses rss feed into listings', function () {
    Http::fake([
        'larajobs.com/feed' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <title>Senior Laravel Developer at Acme Corp</title>
      <link>https://larajobs.com/job/123</link>
      <description>We need a senior Laravel dev. Remote. $120k-$180k.</description>
      <pubDate>Mon, 20 Mar 2026 00:00:00 +0000</pubDate>
    </item>
    <item>
      <title>Junior PHP Developer at StartupCo</title>
      <link>https://larajobs.com/job/456</link>
      <description>Entry level PHP position in NYC.</description>
      <pubDate>Sun, 19 Mar 2026 00:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML),
    ]);

    $scraper = new LarajobsScraper;
    $listings = $scraper->scrape();

    expect($listings)->toHaveCount(2)
        ->and($listings[0]['title'])->toBe('Senior Laravel Developer at Acme Corp')
        ->and($listings[0]['company'])->toBe('Acme Corp')
        ->and($listings[0]['url'])->toBe('https://larajobs.com/job/123')
        ->and($listings[0]['remote'])->toBeTrue()
        ->and($listings[0]['salary_min'])->toBe(120000)
        ->and($listings[0]['salary_max'])->toBe(180000)
        ->and($listings[1]['company'])->toBe('StartupCo')
        ->and($listings[1]['remote'])->toBeFalse();
});

it('returns empty array on failed request', function () {
    Http::fake([
        'larajobs.com/feed' => Http::response('', 500),
    ]);

    $scraper = new LarajobsScraper;

    expect($scraper->scrape())->toBeEmpty();
});

it('returns empty array on invalid xml', function () {
    Http::fake([
        'larajobs.com/feed' => Http::response('not xml at all'),
    ]);

    $scraper = new LarajobsScraper;

    expect($scraper->scrape())->toBeEmpty();
});

it('extracts company from @ syntax', function () {
    Http::fake([
        'larajobs.com/feed' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <title>Developer @ CoolCo</title>
      <link>https://larajobs.com/job/789</link>
      <description>A job.</description>
      <pubDate>Mon, 20 Mar 2026 00:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML),
    ]);

    $scraper = new LarajobsScraper;
    $listings = $scraper->scrape();

    expect($listings[0]['company'])->toBe('CoolCo');
});
