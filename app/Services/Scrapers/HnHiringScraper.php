<?php

namespace App\Services\Scrapers;

use Generator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HnHiringScraper implements ScraperInterface
{
    use ParsesSalary;

    protected string $searchUrl = 'https://hn.algolia.com/api/v1/search';

    protected string $searchByDateUrl = 'https://hn.algolia.com/api/v1/search_by_date';

    protected int $hitsPerPage = 1000;

    /**
     * @return Generator<int, array{title: string, company: string, url: string, source_url: string, description: string, salary_min: int|null, salary_max: int|null, remote: bool, raw_data: array<string, mixed>}>
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

        /** @var array<int, array<string, mixed>> $hits */
        $hits = $response->json('hits') ?? [];

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
     * @param  array<string, mixed>  $hit
     * @return array{title: string, company: string, url: string, source_url: string, description: string, salary_min: int|null, salary_max: int|null, remote: bool, raw_data: array<string, mixed>}|null
     */
    protected function parseHit(array $hit, int $storyId): ?array
    {
        $hnId = (string) ($hit['objectID'] ?? '');
        $commentHtml = (string) ($hit['comment_text'] ?? '');

        if ($hnId === '' || $commentHtml === '') {
            return null;
        }

        $sourceUrl = "https://news.ycombinator.com/item?id={$hnId}";
        $applyUrl = $this->extractApplyTarget($commentHtml, $sourceUrl);

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
            'url' => $applyUrl,
            'source_url' => $sourceUrl,
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

    /**
     * Pick the most plausible "apply here" link from a HN hiring comment.
     * Prefers anchors whose href or text contains apply-keywords; skips social
     * and HN self-links; falls back to first email as mailto: then to $fallback.
     */
    protected function extractApplyTarget(string $html, string $fallback): string
    {
        $applyKeywords = ['apply', 'careers', 'jobs', 'hiring', 'join', 'work-with-us'];
        $blockedHosts = [
            'twitter.com', 'x.com', 'linkedin.com', 'facebook.com',
            'github.com', 'news.ycombinator.com', 'ycombinator.com',
        ];

        if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', $html, $matches, PREG_SET_ORDER)) {
            $candidates = [];
            foreach ($matches as $m) {
                $href = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);

                if (! Str::startsWith($href, ['http://', 'https://'])) {
                    continue;
                }

                if (Str::contains($href, $blockedHosts, true)) {
                    continue;
                }

                $haystack = strtolower($href.' '.strip_tags($m[2]));
                $candidates[] = [
                    'href' => $href,
                    'score' => Str::contains($haystack, $applyKeywords) ? 2 : 1,
                ];
            }

            if ($candidates !== []) {
                usort($candidates, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

                return $candidates[0]['href'];
            }
        }

        if (preg_match('/[\w._%+-]+@[\w.-]+\.[A-Za-z]{2,}/', strip_tags($html), $em)) {
            return 'mailto:'.$em[0];
        }

        return $fallback;
    }

    protected function htmlToText(string $html): string
    {
        $withBreaks = (string) preg_replace(['/<p>/i', '/<br\s*\/?>/i'], ["\n\n", "\n"], $html);

        return trim(html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5));
    }
}
