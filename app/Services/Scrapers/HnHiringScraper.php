<?php

namespace App\Services\Scrapers;

use DOMDocument;
use DOMXPath;
use Generator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HnHiringScraper implements ScraperInterface
{
    use ParsesSalary;

    protected string $url = 'https://nchelluri.github.io/hnjobs/';

    /**
     * @return Generator<int, array{title: string, company: string, url: string, description: string, salary_min: int|null, salary_max: int|null, remote: bool, raw_data: array<string, mixed>}>
     */
    public function scrape(): Generator
    {
        $response = Http::get($this->url);

        if (! $response->ok()) {
            return;
        }

        $html = $response->body();
        unset($response);

        $dom = new DOMDocument;
        @$dom->loadHTML($html, LIBXML_NOERROR);
        unset($html);

        $xpath = new DOMXPath($dom);
        $comments = $xpath->query('//div[contains(@class, "content") and not(@style)]');

        if (! $comments || $comments->length === 0) {
            return;
        }

        foreach ($comments as $comment) {
            if (! $comment instanceof \DOMElement) {
                continue;
            }

            $parsed = $this->parseComment($comment, $xpath);

            if ($parsed) {
                yield $parsed;
            }
        }
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
        $url = $linkNode instanceof \DOMElement ? $linkNode->getAttribute('href') : "https://news.ycombinator.com/item?id={$hnId}";

        $commentHtml = $comment->ownerDocument->saveHTML($comment);
        $decoded = html_entity_decode($commentHtml);
        $decoded = preg_replace('/<br\s*\/?>/i', "\n", $decoded);
        $decoded = preg_replace('/<\/p>\s*<p>/i', "\n\n", $decoded);
        $decoded = preg_replace('/<\/?p>/i', "\n", $decoded);
        $text = strip_tags($decoded);
        $text = preg_replace('/\s*×\s*/', '', $text);
        $text = trim(preg_replace('/by \S+\s*Original Post.*?UTC\s*(Prev\s*\|?\s*)?(Next\s*)?\s*/s', '', $text));

        if (Str::length($text) < 10) {
            return null;
        }

        $firstLine = Str::before($text, "\n");
        $parts = array_map('trim', explode('|', $firstLine));

        if (count($parts) >= 2) {
            $company = $parts[0];
            $title = $parts[1];
        } else {
            $company = 'Unknown';
            $title = $firstLine;
        }

        if (empty($company)) {
            $company = 'Unknown';
        }

        $remote = Str::contains($text, 'remote', true);
        $salary = $this->parseSalary($text);

        return [
            'title' => Str::limit($title, 200),
            'company' => Str::limit($company, 100),
            'url' => $url,
            'description' => $text,
            'salary_min' => $salary['min'],
            'salary_max' => $salary['max'],
            'remote' => $remote,
            'raw_data' => [
                'hn_id' => $hnId,
            ],
        ];
    }
}
