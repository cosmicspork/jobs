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
    ],

    'larajobs' => [
        'name' => 'Larajobs',
        'scraper' => LarajobsScraper::class,
        'enabled' => true,
    ],

    'remoteok' => [
        'name' => 'RemoteOK',
        'scraper' => RemoteOkScraper::class,
        'enabled' => true,
    ],

    'wwr' => [
        'name' => 'We Work Remotely',
        'scraper' => WeWorkRemotelyScraper::class,
        'enabled' => true,
    ],

];
