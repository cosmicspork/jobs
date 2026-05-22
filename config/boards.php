<?php

use App\Services\Scrapers\HnHiringScraper;
use App\Services\Scrapers\LarajobsScraper;
use App\Services\Scrapers\RemoteOkScraper;
use App\Services\Scrapers\WeWorkRemotelyScraper;

return [

    'hn' => [
        'name' => 'Hacker News - Who is Hiring',
        'scraper' => HnHiringScraper::class,
        'enabled' => true,
        'requires_enrichment' => false,
    ],

    'larajobs' => [
        'name' => 'Larajobs',
        'scraper' => LarajobsScraper::class,
        'enabled' => true,
        // LaraJobs RSS only publishes title + structured metadata; the real
        // description lives at the linked ATS or career page. EnrichListing
        // resolves the redirect and rewrites listings.description before
        // scoring runs.
        'requires_enrichment' => true,
    ],

    'remoteok' => [
        'name' => 'RemoteOK',
        'scraper' => RemoteOkScraper::class,
        'enabled' => true,
        'requires_enrichment' => false,
    ],

    'wwr' => [
        'name' => 'We Work Remotely',
        'scraper' => WeWorkRemotelyScraper::class,
        'enabled' => true,
        'requires_enrichment' => false,
    ],

];
