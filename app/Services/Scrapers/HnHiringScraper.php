<?php

namespace App\Services\Scrapers;

use Generator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HnHiringScraper implements ScraperInterface
{
    use ParsesSalary;

    protected string $searchUrl = 'https://hn.algolia.com/api/v1/search';

    protected string $searchByDateUrl = 'https://hn.algolia.com/api/v1/search_by_date';

    protected int $hitsPerPage = 1000;

    /**
     * @return Generator<int, array{title: string, company: string, url: string, description: string, salary_min: int|null, salary_max: int|null, remote: bool, raw_data: array<string, mixed>}>
     */
    public function scrape(): Generator
    {
        $storyId = $this->latestHiringStoryId();

        if ($storyId === null) {
            return;
        }

        $page = 0;

        do {
            $response = Http::get($this->searchByDateUrl, [
                'tags' => "comment,story_{$storyId}",
                'numericFilters' => "parent_id={$storyId}",
                'hitsPerPage' => $this->hitsPerPage,
                'page' => $page,
            ]);

            if (! $response->ok()) {
                return;
            }

            /** @var array{hits?: array<int, array<string, mixed>>, nbPages?: int} $data */
            $data = $response->json() ?? [];
            $hits = $data['hits'] ?? [];

            foreach ($hits as $hit) {
                $parsed = $this->parseHit($hit, $storyId);

                if ($parsed !== null) {
                    yield $parsed;
                }
            }

            $totalPages = (int) ($data['nbPages'] ?? 0);
            $page++;
        } while ($page < $totalPages);
    }

    protected function latestHiringStoryId(): ?int
    {
        $response = Http::get($this->searchByDateUrl, [
            'tags' => 'story,author_whoishiring',
            'query' => 'Ask HN: Who is hiring',
            'hitsPerPage' => 10,
        ]);

        if (! $response->ok()) {
            return null;
        }

        $hits = $this->hitsFrom($response);

        foreach ($hits as $hit) {
            $title = (string) ($hit['title'] ?? '');

            if (Str::contains($title, 'Who is hiring', true)) {
                $id = (int) ($hit['objectID'] ?? 0);

                return $id > 0 ? $id : null;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function hitsFrom(Response $response): array
    {
        /** @var array<int, array<string, mixed>> $hits */
        $hits = $response->json('hits') ?? [];

        return $hits;
    }

    /**
     * @param  array<string, mixed>  $hit
     * @return array{title: string, company: string, url: string, description: string, salary_min: int|null, salary_max: int|null, remote: bool, raw_data: array<string, mixed>}|null
     */
    protected function parseHit(array $hit, int $storyId): ?array
    {
        $hnId = (string) ($hit['objectID'] ?? '');
        $commentHtml = (string) ($hit['comment_text'] ?? '');

        if ($hnId === '' || $commentHtml === '') {
            return null;
        }

        $text = $this->htmlToText($commentHtml);

        if (Str::length($text) < 10) {
            return null;
        }

        $firstLine = trim(Str::before($text, "\n"));
        $parts = array_map('trim', explode('|', $firstLine));

        if (count($parts) >= 2) {
            $company = $parts[0];
            $title = $parts[1];
        } else {
            $company = 'Unknown';
            $title = $firstLine;
        }

        if ($company === '') {
            $company = 'Unknown';
        }

        $remote = Str::contains($text, 'remote', true);
        $salary = $this->parseSalary($text);

        return [
            'title' => Str::limit($title, 200),
            'company' => Str::limit($company, 100),
            'url' => "https://news.ycombinator.com/item?id={$hnId}",
            'description' => $text,
            'salary_min' => $salary['min'],
            'salary_max' => $salary['max'],
            'remote' => $remote,
            'raw_data' => [
                'hn_id' => $hnId,
                'story_id' => $storyId,
                'author' => (string) ($hit['author'] ?? ''),
                'created_at' => (string) ($hit['created_at'] ?? ''),
            ],
        ];
    }

    protected function htmlToText(string $html): string
    {
        $replaced = preg_replace('/<p>/i', "\n\n", $html) ?? $html;
        $replaced = preg_replace('/<br\s*\/?>/i', "\n", $replaced) ?? $replaced;
        $text = strip_tags($replaced);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);

        return trim($text);
    }
}
