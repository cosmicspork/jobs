<?php

namespace App\Services\Enrichment\Adapters;

use App\Services\Enrichment\EnrichmentAdapter;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use Illuminate\Support\Facades\Http;

/**
 * Fallback adapter: works against any HTML career page. Strips boilerplate
 * (scripts, nav, header, footer, aside), narrows to the most-likely content
 * region (<main>, <article>, the largest <div>), and converts a small set of
 * tags to markdown-ish text. The output is intended to be LLM-readable, not
 * pixel-perfect.
 */
class GenericHtmlAdapter implements EnrichmentAdapter
{
    public function source(): string
    {
        return 'generic_html';
    }

    public function supports(string $finalUrl): bool
    {
        // Catch-all: only run after specialized adapters have declined.
        return true;
    }

    public function extract(string $finalUrl): ?string
    {
        $response = Http::withHeaders([
            'User-Agent' => config('app.name').' enrichment (+https://github.com/cosmicspork/jobs)',
            'Accept' => 'text/html,application/xhtml+xml',
        ])->get($finalUrl);

        if (! $response->ok()) {
            return null;
        }

        $contentType = $response->header('Content-Type') ?: '';

        if ($contentType !== '' && ! str_contains(strtolower($contentType), 'html')) {
            return null;
        }

        return $this->htmlToMarkdown($response->body());
    }

    public function htmlToMarkdown(string $html): ?string
    {
        if (trim($html) === '') {
            return null;
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);

        // Drop chrome: scripts, styles, navigation, branding shell.
        foreach ($xpath->query('//script | //style | //noscript | //nav | //header | //footer | //aside | //form | //iframe') as $node) {
            $node->parentNode?->removeChild($node);
        }

        $root = $this->pickContentRoot($xpath, $doc);

        if (! $root instanceof DOMNode) {
            return null;
        }

        $markdown = trim($this->renderNode($root));
        $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown) ?? $markdown;

        return $markdown !== '' ? $markdown : null;
    }

    private function pickContentRoot(DOMXPath $xpath, DOMDocument $doc): ?DOMNode
    {
        foreach (['//main', '//article'] as $expression) {
            $nodes = $xpath->query($expression);

            if ($nodes && $nodes->length > 0) {
                return $nodes->item(0);
            }
        }

        // Fallback: largest <div> by text length. Captures career pages that
        // don't use semantic landmarks.
        $best = null;
        $bestLength = 0;
        foreach ($xpath->query('//div') as $node) {
            $length = strlen(trim($node->textContent));
            if ($length > $bestLength) {
                $bestLength = $length;
                $best = $node;
            }
        }

        return $best ?? $doc->getElementsByTagName('body')->item(0);
    }

    private function renderNode(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return preg_replace('/\s+/', ' ', $node->textContent) ?? '';
        }

        if (! $node instanceof DOMElement) {
            return $this->renderChildren($node);
        }

        $tag = strtolower($node->nodeName);
        $inner = $this->renderChildren($node);

        return match ($tag) {
            'h1' => "\n\n# ".trim($inner)."\n\n",
            'h2' => "\n\n## ".trim($inner)."\n\n",
            'h3' => "\n\n### ".trim($inner)."\n\n",
            'h4', 'h5', 'h6' => "\n\n#### ".trim($inner)."\n\n",
            'p' => "\n\n".trim($inner)."\n\n",
            'br' => "\n",
            'li' => '- '.trim($inner)."\n",
            'ul', 'ol' => "\n".$inner."\n",
            'strong', 'b' => '**'.trim($inner).'**',
            'em', 'i' => '*'.trim($inner).'*',
            'a' => $this->renderLink($node, $inner),
            'code' => '`'.trim($inner).'`',
            'pre' => "\n\n```\n".trim($inner)."\n```\n\n",
            'blockquote' => "\n> ".trim($inner)."\n",
            'hr' => "\n\n---\n\n",
            default => $inner,
        };
    }

    private function renderChildren(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= $this->renderNode($child);
        }

        return $out;
    }

    private function renderLink(DOMElement $node, string $inner): string
    {
        $href = $node->getAttribute('href');
        $text = trim($inner);

        if ($text === '') {
            return '';
        }

        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
            return $text;
        }

        return "[{$text}]({$href})";
    }
}
