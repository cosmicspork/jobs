<?php

namespace App\Services\Scrapers;

interface ScraperInterface
{
    /**
     * Scrape job listings from the board.
     *
     * @return iterable<int, array{
     *     title: string,
     *     company: string,
     *     url: string,
     *     description: string,
     *     salary_min: int|null,
     *     salary_max: int|null,
     *     remote: bool,
     *     raw_data: array<string, mixed>,
     * }>
     */
    public function scrape(): iterable;
}
