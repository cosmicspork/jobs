<?php

namespace App\Services\Enrichment\Adapters;

use App\Services\Enrichment\EnrichmentAdapter;
use Illuminate\Support\Facades\Http;

class WorkableAdapter implements EnrichmentAdapter
{
    public function source(): string
    {
        return 'workable_md';
    }

    public function supports(string $finalUrl): bool
    {
        return str_contains(parse_url($finalUrl, PHP_URL_HOST) ?? '', 'workable.com');
    }

    public function extract(string $finalUrl): ?string
    {
        $html = Http::withHeaders($this->headers())->get($finalUrl);

        if (! $html->ok()) {
            return null;
        }

        // Workable advertises a markdown rendering of each listing via
        // <link rel="alternate" type="text/markdown" href="...">. Prefer
        // that — it's effectively an officially-sanctioned LLM ingestion
        // endpoint, ~13KB of structured markdown vs. ~150KB of JS-heavy HTML.
        if (! preg_match(
            '/<link\b[^>]*\brel=["\']alternate["\'][^>]*\btype=["\']text\/markdown["\'][^>]*\bhref=["\']([^"\']+)["\']/i',
            $html->body(),
            $matches,
        )) {
            return null;
        }

        $markdownUrl = $matches[1];
        $md = Http::withHeaders($this->headers())->get($markdownUrl);

        if (! $md->ok()) {
            return null;
        }

        $body = trim($md->body());

        return $body !== '' ? $body : null;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'User-Agent' => config('app.name').' enrichment (+https://github.com/cosmicspork/jobs)',
            'Accept' => 'text/html,text/markdown,application/xhtml+xml',
        ];
    }
}
