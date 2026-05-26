<?php

use App\Services\Scrapers\LarajobsScraper;
use Illuminate\Support\Facades\Http;

it('parses rss feed into listings', function () {
    Http::fake([
        'larajobs.com/feed' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:job="https://larajobs.com/job">
  <channel>
    <item>
      <title>Senior Laravel Developer</title>
      <link>https://larajobs.com/job/123</link>
      <description>We need a senior Laravel dev.</description>
      <pubDate>Mon, 20 Mar 2026 00:00:00 +0000</pubDate>
      <job:company>Acme Corp</job:company>
      <job:location>Remote US</job:location>
      <job:job_type>FULL_TIME</job:job_type>
      <job:salary>$120,000 - $180,000 USD</job:salary>
      <job:tags>Laravel,PHP,Remote</job:tags>
    </item>
    <item>
      <title>Junior PHP Developer</title>
      <link>https://larajobs.com/job/456</link>
      <description>Entry level PHP position in NYC.</description>
      <pubDate>Sun, 19 Mar 2026 00:00:00 +0000</pubDate>
      <job:company>StartupCo</job:company>
      <job:location>New York, NY</job:location>
      <job:job_type>FULL_TIME</job:job_type>
      <job:salary></job:salary>
      <job:tags>PHP,Junior</job:tags>
    </item>
  </channel>
</rss>
XML),
    ]);

    $scraper = new LarajobsScraper;
    $listings = iterator_to_array($scraper->scrape());

    expect($listings)->toHaveCount(2)
        ->and($listings[0]['title'])->toBe('Senior Laravel Developer')
        ->and($listings[0]['company'])->toBe('Acme Corp')
        ->and($listings[0]['url'])->toBe('https://larajobs.com/job/123')
        ->and($listings[0]['source_url'])->toBe('https://larajobs.com/job/123')
        ->and($listings[0]['remote'])->toBeTrue()
        ->and($listings[0]['salary_min'])->toBe(120000)
        ->and($listings[0]['salary_max'])->toBe(180000)
        ->and($listings[0]['description'])->toContain('Location: Remote US')
        ->and($listings[0]['description'])->toContain('Tags: Laravel,PHP,Remote')
        ->and($listings[1]['company'])->toBe('StartupCo')
        ->and($listings[1]['remote'])->toBeFalse();
});

it('returns empty array on failed request', function () {
    Http::fake([
        'larajobs.com/feed' => Http::response('', 500),
    ]);

    $scraper = new LarajobsScraper;

    expect(iterator_to_array($scraper->scrape()))->toBeEmpty();
});

it('returns empty array on connection failure', function () {
    Http::fake([
        'larajobs.com/feed' => Http::failedConnection(),
    ]);

    expect(iterator_to_array((new LarajobsScraper)->scrape()))->toBeEmpty();
});

it('returns empty array on invalid xml', function () {
    Http::fake([
        'larajobs.com/feed' => Http::response('not xml at all'),
    ]);

    $scraper = new LarajobsScraper;

    expect(iterator_to_array($scraper->scrape()))->toBeEmpty();
});

it('uses job:company from feed over title extraction', function () {
    Http::fake([
        'larajobs.com/feed' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:job="https://larajobs.com/job">
  <channel>
    <item>
      <title>Developer @ CoolCo</title>
      <link>https://larajobs.com/job/789</link>
      <description>A job.</description>
      <pubDate>Mon, 20 Mar 2026 00:00:00 +0000</pubDate>
      <job:company>CoolCo Official</job:company>
      <job:location>Remote</job:location>
      <job:job_type>FULL_TIME</job:job_type>
      <job:salary></job:salary>
      <job:tags>PHP</job:tags>
    </item>
  </channel>
</rss>
XML),
    ]);

    $scraper = new LarajobsScraper;
    $listings = iterator_to_array($scraper->scrape());

    expect($listings[0]['company'])->toBe('CoolCo Official');
});

it('falls back to title extraction when job:company is missing', function () {
    Http::fake([
        'larajobs.com/feed' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:job="https://larajobs.com/job">
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
    $listings = iterator_to_array($scraper->scrape());

    expect($listings[0]['company'])->toBe('CoolCo');
});
