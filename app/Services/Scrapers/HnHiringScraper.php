<?php

namespace App\Services\Scrapers;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HnHiringScraper implements ScraperInterface
{
    use ParsesSalary;

    protected string $url = 'https://nchelluri.github.io/hnjobs/';

    /**
     * @return array<int, array{title: string, company: string, url: string, description: string, salary_min: int|null, salary_max: int|null, remote: bool, raw_data: array<string, mixed>}>
     */
    public function scrape(): array
    {
        $response = Http::get($this->url);

        if (! $response->ok()) {
            return [];
        }

        $dom = new DOMDocument;
        @$dom->loadHTML($response->body(), LIBXML_NOERROR);
        $xpath = new DOMXPath($dom);

        $comments = $xpath->query('//div[contains(@class, "content") and not(@style)]');

        if (! $comments || $comments->length === 0) {
            return [];
        }

        $listings = [];

        foreach ($comments as $comment) {
            $parsed = $this->parseComment($comment, $xpath);

            if ($parsed) {
                $listings[] = $parsed;
            }
        }

        return $listings;
    }

    /**
     * @return array{title: string, company: string, url: string, description: string, salary_min: int|null, salary_max: int|null, remote: bool, raw_data: array<string, mixed>}|null
     */
    protected function parseComment(\DOMElement $comment, DOMXPath $xpath): ?array
    {
        $id = $comment->getAttribute('id');
        $hnId = Str::after($id, 'comment_');

        if (empty($hnId)) {
            return null;
        }

        $linkNode = $xpath->query('.//small/a[contains(@href, "news.ycombinator.com/item")]', $comment)->item(0);
        $url = $linkNode ? $linkNode->getAttribute('href') : "https://news.ycombinator.com/item?id={$hnId}";

        $html = $comment->ownerDocument->saveHTML($comment);
        $text = strip_tags(html_entity_decode($html));
        $text = preg_replace('/\s*×\s*/', '', $text);
        $text = trim(preg_replace('/by \S+\s*Original Post.*?UTC\s*(Prev\s*\|?\s*)?(Next\s*)?\s*/s', '', $text));

        if (Str::length($text) < 10) {
            return null;
        }

        $firstLine = Str::before($text, "\n");
        $company = trim(Str::before($firstLine, '|'));

        if (empty($company)) {
            $company = 'Unknown';
        }

        $remote = Str::contains($text, 'remote', true);
        $salary = $this->parseSalary($text);

        return [
            'title' => Str::limit($firstLine, 200),
            'company' => Str::limit($company, 100),
            'url' => $url,
            'description' => $text,
            'salary_min' => $salary['min'],
            'salary_max' => $salary['max'],
            'remote' => $remote,
            'raw_data' => [
                'hn_id' => $hnId,
                'html' => $html,
            ],
        ];
    }
}
