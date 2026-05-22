<?php

namespace App\Services\Enrichment;

use App\Models\Listing;
use App\Services\Enrichment\Adapters\GenericHtmlAdapter;
use App\Services\Enrichment\Adapters\WorkableAdapter;
use Illuminate\Support\Facades\Http;

/**
 * @phpstan-type EnrichmentResult array{markdown: string|null, source: string, final_url: string}
 */
class ListingEnricher
{
    /** @var array<int, EnrichmentAdapter> */
    private array $adapters;

    public function __construct(?WorkableAdapter $workable = null, ?GenericHtmlAdapter $generic = null)
    {
        // Specialized adapters first; generic catch-all last.
        $this->adapters = [
            $workable ?? new WorkableAdapter,
            $generic ?? new GenericHtmlAdapter,
        ];
    }

    /**
     * Resolve the listing's URL through any redirects, pick the first
     * adapter that supports the final destination, and extract content.
     *
     * @return EnrichmentResult
     */
    public function enrich(Listing $listing): array
    {
        $finalUrl = $this->resolveFinalUrl($listing->url);

        foreach ($this->adapters as $adapter) {
            if (! $adapter->supports($finalUrl)) {
                continue;
            }

            $markdown = $adapter->extract($finalUrl);

            if ($markdown !== null && trim($markdown) !== '') {
                return [
                    'markdown' => $markdown,
                    'source' => $adapter->source(),
                    'final_url' => $finalUrl,
                ];
            }
        }

        return [
            'markdown' => null,
            'source' => 'none',
            'final_url' => $finalUrl,
        ];
    }

    private function resolveFinalUrl(string $url): string
    {
        $response = Http::withHeaders([
            'User-Agent' => config('app.name').' enrichment (+https://github.com/cosmicspork/jobs)',
        ])->withOptions(['allow_redirects' => ['max' => 5, 'track_redirects' => true]])->head($url);

        if (! $response->ok()) {
            return $url;
        }

        $redirected = $response->header('X-Guzzle-Redirect-History');

        if ($redirected) {
            // Guzzle joins history with ", "; final URL is the last entry.
            $history = array_map('trim', explode(',', $redirected));
            $last = end($history);

            if ($last !== false && $last !== '') {
                return $last;
            }
        }

        return $url;
    }
}
