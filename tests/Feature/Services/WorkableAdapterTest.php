<?php

use App\Services\Enrichment\Adapters\WorkableAdapter;
use Illuminate\Support\Facades\Http;

it('only supports apply.workable.com URLs', function () {
    $adapter = new WorkableAdapter;

    expect($adapter->supports('https://apply.workable.com/chirocat/j/ABC'))->toBeTrue()
        ->and($adapter->supports('https://boards.greenhouse.io/something'))->toBeFalse()
        ->and($adapter->supports('https://example.com'))->toBeFalse();
});

it('fetches the markdown alternate link and returns its body', function () {
    Http::fake([
        'apply.workable.com/chirocat/j/ABC' => Http::response(<<<'HTML'
<!DOCTYPE html>
<html>
<head>
  <link rel="alternate" type="text/markdown"
        href="https://apply.workable.com/chirocat/jobs/view/ABC.md"/>
</head>
<body>JS-heavy app shell</body>
</html>
HTML, 200),
        'apply.workable.com/chirocat/jobs/view/ABC.md' => Http::response("# Senior Engineer\n\nWe're hiring.\n", 200),
    ]);

    $markdown = (new WorkableAdapter)->extract('https://apply.workable.com/chirocat/j/ABC');

    expect($markdown)->toContain('# Senior Engineer')
        ->and($markdown)->toContain("We're hiring");
});

it('returns null when no markdown alternate link is present', function () {
    Http::fake([
        'apply.workable.com/*' => Http::response('<html><body>no link here</body></html>', 200),
    ]);

    expect((new WorkableAdapter)->extract('https://apply.workable.com/foo/j/X'))->toBeNull();
});

it('returns null when the markdown endpoint fails', function () {
    Http::fake([
        'apply.workable.com/foo' => Http::response('<link rel="alternate" type="text/markdown" href="https://apply.workable.com/foo.md"/>', 200),
        'apply.workable.com/foo.md' => Http::response('', 404),
    ]);

    expect((new WorkableAdapter)->extract('https://apply.workable.com/foo'))->toBeNull();
});
