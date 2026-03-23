<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LarajobsScraper implements ScraperInterface
{
    use ParsesSalary;

    protected string $feedUrl = 'https://larajobs.com/feed';

    /**
     * @return array<int, array{title: string, company: string, url: string, description: string, salary_min: int|null, salary_max: int|null, remote: bool, raw_data: array<string, mixed>}>
     */
    public function scrape(): array
    {
        $response = Http::get($this->feedUrl);

        if (! $response->ok()) {
            return [];
        }

        try {
            $xml = simplexml_load_string($response->body());
        } catch (\ErrorException) {
            return [];
        }

        if ($xml === false) {
            return [];
        }

        $listings = [];

        foreach ($xml->channel->item as $item) {
            $title = (string) $item->title;
            $link = (string) $item->link;
            $description = strip_tags(html_entity_decode((string) $item->description));
            $company = $this->extractCompany($title);
            $remote = Str::contains($title.' '.$description, 'remote', true);
            $salary = $this->parseSalary($title.' '.$description);

            $listings[] = [
                'title' => $title,
                'company' => $company,
                'url' => $link,
                'description' => $description,
                'salary_min' => $salary['min'],
                'salary_max' => $salary['max'],
                'remote' => $remote,
                'raw_data' => [
                    'title' => $title,
                    'link' => $link,
                    'description' => (string) $item->description,
                    'pubDate' => (string) $item->pubDate,
                ],
            ];
        }

        return $listings;
    }

    protected function extractCompany(string $title): string
    {
        if (Str::contains($title, ' at ')) {
            return trim(Str::after($title, ' at '));
        }

        if (Str::contains($title, ' @ ')) {
            return trim(Str::after($title, ' @ '));
        }

        return 'Unknown';
    }
}
