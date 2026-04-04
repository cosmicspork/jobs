<?php

namespace App\Services\Scrapers;

use Generator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LarajobsScraper implements ScraperInterface
{
    use ParsesSalary;

    protected string $feedUrl = 'https://larajobs.com/feed';

    /**
     * @return Generator<int, array{title: string, company: string, url: string, description: string, salary_min: int|null, salary_max: int|null, remote: bool, raw_data: array<string, mixed>}>
     */
    public function scrape(): Generator
    {
        $response = Http::get($this->feedUrl);

        if (! $response->ok()) {
            return;
        }

        try {
            $xml = simplexml_load_string($response->body());
        } catch (\ErrorException) {
            return;
        }

        unset($response);

        if ($xml === false) {
            return;
        }

        foreach ($xml->channel->item as $item) {
            $job = $item->children('job', true);

            $title = (string) $item->title;
            $link = (string) $item->link;
            $company = (string) ($job->company ?? '') ?: $this->extractCompany($title);
            $location = (string) ($job->location ?? '');
            $jobType = (string) ($job->job_type ?? '');
            $tags = (string) ($job->tags ?? '');
            $salaryText = (string) ($job->salary ?? '');

            $description = implode("\n", array_filter([
                $title,
                $company !== 'Unknown' ? "Company: {$company}" : '',
                $location ? "Location: {$location}" : '',
                $jobType ? "Type: {$jobType}" : '',
                $tags ? "Tags: {$tags}" : '',
                $salaryText ? "Salary: {$salaryText}" : '',
            ]));

            $searchable = $title.' '.$location.' '.$tags;
            $remote = Str::contains($searchable, 'remote', true);
            $salary = $this->parseSalary($salaryText ?: $searchable);

            yield [
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
                    'pubDate' => (string) $item->pubDate,
                    'company' => $company,
                    'location' => $location,
                    'job_type' => $jobType,
                    'tags' => $tags,
                    'salary' => $salaryText,
                ],
            ];
        }
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
