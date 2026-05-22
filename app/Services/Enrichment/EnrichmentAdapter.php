<?php

namespace App\Services\Enrichment;

interface EnrichmentAdapter
{
    /**
     * Short identifier written to listings.enrichment_source. Useful for
     * later diagnosing which adapter produced a given description.
     */
    public function source(): string;

    /**
     * Whether this adapter recognizes and can extract content from the given
     * resolved final URL. Adapters are tried in priority order; the first
     * one that returns true is used.
     */
    public function supports(string $finalUrl): bool;

    /**
     * Fetch the URL and return the extracted description as markdown, or
     * null if extraction failed. Implementations are responsible for making
     * any additional HTTP requests they need (e.g. probing for a markdown
     * alternate link).
     */
    public function extract(string $finalUrl): ?string;
}
