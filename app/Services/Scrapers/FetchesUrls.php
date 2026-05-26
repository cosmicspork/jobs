<?php

namespace App\Services\Scrapers;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait FetchesUrls
{
    /**
     * Send an HTTP request with shared timeout/retry, returning null instead of
     * throwing on connection failure so one unreachable feed cannot crash the
     * scrape job. HTTP status checks remain the caller's responsibility.
     *
     * @param  Closure(PendingRequest): Response  $send
     */
    protected function fetch(Closure $send): ?Response
    {
        try {
            return $send(Http::timeout(15)->retry(2, 250, throw: false));
        } catch (ConnectionException $e) {
            Log::warning('Scraper request failed', [
                'scraper' => static::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
