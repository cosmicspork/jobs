<?php

use App\Services\Scrapers\HnHiringScraper;
use App\Services\Scrapers\LarajobsScraper;

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

];
