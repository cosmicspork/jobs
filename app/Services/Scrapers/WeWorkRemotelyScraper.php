<?php

namespace App\Services\Scrapers;

use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;

/**
 * We Work Remotely scraper.
 *
 * The aggregate /remote-job-rss-feed is Cloudflare-challenged and returns 403
 * for non-browser clients. Per-category feeds at /categories/<slug>.rss are
 * not challenged. We aggregate across engineering-relevant categories and
 * dedupe by guid; the three programming children replace the parent to avoid
 * 4x overlap. Non-engineering categories (design, customer support, sales &
 * marketing, product) are intentionally excluded — they never match the
 * engineering/management targets this app scores for, and only inflate the
 * funnel with listings that get filtered straight to irrelevant.
 */
class WeWorkRemotelyScraper implements ScraperInterface
{
    use FetchesUrls;
    use ParsesSalary;

    /** @var array<int, string> */
    protected array $categories = [
        'remote-back-end-programming-jobs',
        'remote-front-end-programming-jobs',
        'remote-full-stack-programming-jobs',
        'remote-devops-sysadmin-jobs',
        'remote-management-and-finance-jobs',
        'all-other-remote-jobs',
    ];

    /**
     * @return Generator<int, array{title: string, company: string, url: string, source_url: string, description: string, salary_min: int|null, salary_max: int|null, remote: bool, raw_data: array<string, mixed>}>
     */
    public function scrape(): Generator
    {
        /** @var array<string, true> $seen */
        $seen = [];

        foreach ($this->categories as $slug) {
            $response = $this->fetch(fn (PendingRequest $http) => $http->get("https://weworkremotely.com/categories/{$slug}.rss"));

            if ($response === null || ! $response->ok()) {
                continue;
            }

            try {
                $xml = simplexml_load_string($response->body());
            } catch (\ErrorException) {
                continue;
            }

            if ($xml === false) {
                continue;
            }

            foreach ($xml->channel->item as $item) {
                $guid = (string) $item->guid;

                if ($guid === '' || isset($seen[$guid])) {
                    continue;
                }

                $seen[$guid] = true;

                $rawTitle = trim((string) $item->title);
                [$company, $title] = $this->splitTitle($rawTitle);
                $link = (string) $item->link;
                $description = (string) $item->description;

                $salary = $this->parseSalary($description);
                $descText = trim(html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5));

                yield [
                    'title' => Str::limit($title, 200),
                    'company' => Str::limit($company, 100),
                    'url' => $link,
                    'source_url' => $link,
                    'description' => $descText,
                    'salary_min' => $salary['min'],
                    'salary_max' => $salary['max'],
                    'remote' => true,
                    'raw_data' => [
                        'guid' => $guid,
                        'category' => $slug,
                        'region' => (string) ($item->region ?? ''),
                        'pubDate' => (string) $item->pubDate,
                        'raw_title' => $rawTitle,
                    ],
                ];
            }
        }
    }

    /**
     * WWR titles are formatted as "Company: Position". Split on the first ": ".
     *
     * @return array{0: string, 1: string}
     */
    protected function splitTitle(string $raw): array
    {
        if (str_contains($raw, ': ')) {
            [$company, $title] = explode(': ', $raw, 2);

            return [trim($company), trim($title)];
        }

        return ['Unknown', $raw];
    }
}
