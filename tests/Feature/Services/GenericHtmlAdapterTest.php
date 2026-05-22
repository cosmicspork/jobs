<?php

use App\Services\Enrichment\Adapters\GenericHtmlAdapter;
use Illuminate\Support\Facades\Http;

it('supports any URL as the catch-all fallback', function () {
    $adapter = new GenericHtmlAdapter;

    expect($adapter->supports('https://anywhere.example/jobs'))->toBeTrue();
});

it('extracts main-region content and converts headings, paragraphs and lists to markdown', function () {
    $html = <<<'HTML'
<!DOCTYPE html>
<html><body>
  <nav>Site nav</nav>
  <main>
    <h1>Senior Laravel Developer</h1>
    <p>Build the platform.</p>
    <h2>Requirements</h2>
    <ul>
      <li>5+ years PHP</li>
      <li>Laravel experience</li>
    </ul>
  </main>
  <footer>© 2026</footer>
</body></html>
HTML;

    $md = (new GenericHtmlAdapter)->htmlToMarkdown($html);

    expect($md)->toContain('# Senior Laravel Developer')
        ->and($md)->toContain('## Requirements')
        ->and($md)->toContain('- 5+ years PHP')
        ->and($md)->toContain('- Laravel experience')
        ->and($md)->not->toContain('Site nav')
        ->and($md)->not->toContain('© 2026');
});

it('falls back to the largest div when no main/article landmark is present', function () {
    $html = <<<'HTML'
<!DOCTYPE html>
<html><body>
  <div>tiny aside</div>
  <div>
    <h2>About the role</h2>
    <p>You will own the queue layer.</p>
    <p>You will mentor two engineers.</p>
    <p>You will report to the CTO.</p>
  </div>
</body></html>
HTML;

    $md = (new GenericHtmlAdapter)->htmlToMarkdown($html);

    expect($md)->toContain('## About the role')
        ->and($md)->toContain('queue layer');
});

it('drops scripts, styles, and form chrome', function () {
    $html = <<<'HTML'
<html><body>
  <main>
    <h1>Role</h1>
    <script>alert(1)</script>
    <style>.x{color:red}</style>
    <p>Real content.</p>
    <form><input/></form>
  </main>
</body></html>
HTML;

    $md = (new GenericHtmlAdapter)->htmlToMarkdown($html);

    expect($md)->toContain('Real content.')
        ->and($md)->not->toContain('alert(1)')
        ->and($md)->not->toContain('color:red');
});

it('returns null on a non-HTML response', function () {
    Http::fake([
        'example.com/x' => Http::response('binary stuff', 200, ['Content-Type' => 'application/pdf']),
    ]);

    expect((new GenericHtmlAdapter)->extract('https://example.com/x'))->toBeNull();
});
