<?php

namespace App\Services\Scrapers;

use Generator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * RemoteOK JSON API scraper.
 *
 * Their /rss feed only returns ~7 items; the JSON endpoint returns ~100 with
 * full fields. RemoteOK's API ToS requires a dofollow link back to the
 * source listing page; that link is rendered by ListingInfolist's
 * `source_url` TextEntry. Do not add rel="nofollow" to that entry.
 */
class RemoteOkScraper implements ScraperInterface
{
    use ParsesSalary;

    protected string $endpoint = 'https://remoteok.com/api';

    /**
     * @return Generator<int, array{title: string, company: string, url: string, source_url: string, description: string, salary_min: int|null, salary_max: int|null, remote: bool, raw_data: array<string, mixed>}>
     */
    public function scrape(): Generator
    {
        $response = Http::withHeaders(['User-Agent' => 'jobs-app/1.0 (+https://github.com/cosmicspork/jobs)'])
            ->acceptJson()
            ->get($this->endpoint);

        if (! $response->ok()) {
            Log::warning('RemoteOK scrape failed', [
                'status' => $response->status(),
                'body_preview' => Str::limit($response->body(), 200),
            ]);

            return;
        }

        /** @var array<int, array<string, mixed>> $items */
        $items = $response->json() ?? [];
        array_shift($items); // index 0 is RemoteOK's legal/metadata header

        foreach ($items as $item) {
            $slug = (string) ($item['url'] ?? '');
            $apply = (string) ($item['apply_url'] ?? '');

            if ($slug === '') {
                continue;
            }

            $sourceUrl = $slug;
            $url = $apply !== '' ? $apply : $slug;

            $min = ((int) ($item['salary_min'] ?? 0)) ?: null;
            $max = ((int) ($item['salary_max'] ?? 0)) ?: null;

            if ($min === null && $max === null) {
                $parsed = $this->parseSalary((string) ($item['description'] ?? ''));
                $min = $parsed['min'];
                $max = $parsed['max'];
            }

            $description = trim(html_entity_decode(
                strip_tags((string) ($item['description'] ?? '')),
                ENT_QUOTES | ENT_HTML5
            ));

            yield [
                'title' => Str::limit((string) ($item['position'] ?? ''), 200),
                'company' => Str::limit((string) ($item['company'] ?? 'Unknown'), 100),
                'url' => $url,
                'source_url' => $sourceUrl,
                'description' => $description,
                'salary_min' => $min,
                'salary_max' => $max,
                'remote' => true,
                'raw_data' => [
                    'id' => (string) ($item['id'] ?? ''),
                    'slug' => $slug,
                    'apply_url' => $apply,
                    'location' => (string) ($item['location'] ?? ''),
                    'date' => (string) ($item['date'] ?? ''),
                    'tags' => (array) ($item['tags'] ?? []),
                ],
            ];
        }
    }
}
