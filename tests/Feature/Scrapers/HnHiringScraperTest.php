<?php

use App\Services\Scrapers\HnHiringScraper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function fakeAlgolia(array $story, array $commentPages): void
{
    $commentCursor = 0;

    Http::fake(function (Request $request) use ($story, $commentPages, &$commentCursor) {
        $url = $request->url();

        if (Str::contains($url, 'tags=story%2Cauthor_whoishiring')) {
            return Http::response([
                'hits' => $story === [] ? [] : [$story],
                'nbPages' => $story === [] ? 0 : 1,
            ]);
        }

        if (Str::contains($url, 'tags=comment%2Cstory_')) {
            $page = $commentPages[$commentCursor] ?? ['hits' => [], 'nbPages' => 0];
            $commentCursor++;

            return Http::response($page);
        }

        return Http::response('', 404);
    });
}

it('parses hn hiring comments from algolia into listings', function () {
    fakeAlgolia(
        story: [
            'objectID' => '999',
            'title' => 'Ask HN: Who is hiring? (March 2026)',
        ],
        commentPages: [
            [
                'hits' => [
                    [
                        'objectID' => '100',
                        'author' => 'someone',
                        'created_at' => '2026-03-02T17:07:00Z',
                        'comment_text' => 'Acme Corp | Senior PHP Developer | Remote | $150k-$200k<p>We are hiring a senior PHP developer to work on our platform.',
                    ],
                    [
                        'objectID' => '101',
                        'author' => 'other',
                        'created_at' => '2026-03-02T16:44:00Z',
                        'comment_text' => 'BigCo | Frontend Engineer | NYC | $120k-$160k<p>Looking for a frontend engineer in our NYC office.',
                    ],
                ],
                'nbPages' => 1,
            ],
        ],
    );

    $scraper = new HnHiringScraper;
    $listings = iterator_to_array($scraper->scrape());

    expect($listings)->toHaveCount(2)
        ->and($listings[0]['company'])->toBe('Acme Corp')
        ->and($listings[0]['title'])->toBe('Senior PHP Developer')
        ->and($listings[0]['remote'])->toBeTrue()
        ->and($listings[0]['salary_min'])->toBe(150000)
        ->and($listings[0]['salary_max'])->toBe(200000)
        ->and($listings[0]['url'])->toBe('https://news.ycombinator.com/item?id=100')
        ->and($listings[0]['source_url'])->toBe('https://news.ycombinator.com/item?id=100')
        ->and($listings[0]['raw_data']['hn_id'])->toBe('100')
        ->and($listings[0]['raw_data']['story_id'])->toBe(999)
        ->and($listings[0]['raw_data']['author'])->toBe('someone')
        ->and($listings[1]['company'])->toBe('BigCo')
        ->and($listings[1]['remote'])->toBeFalse();
});

it('decodes html entities and tags in comment bodies', function () {
    fakeAlgolia(
        story: [
            'objectID' => '999',
            'title' => 'Ask HN: Who is hiring? (March 2026)',
        ],
        commentPages: [
            [
                'hits' => [
                    [
                        'objectID' => '100',
                        'author' => 'someone',
                        'created_at' => '2026-03-02T17:07:00Z',
                        'comment_text' => 'Acme &amp; Co | Engineer<p>Apply at <a href="https://acme.example">acme.example</a>&#x2F;careers',
                    ],
                ],
                'nbPages' => 1,
            ],
        ],
    );

    $scraper = new HnHiringScraper;
    $listings = iterator_to_array($scraper->scrape());

    expect($listings)->toHaveCount(1)
        ->and($listings[0]['company'])->toBe('Acme & Co')
        ->and($listings[0]['description'])->toContain('acme.example/careers')
        ->and($listings[0]['description'])->not->toContain('<a href')
        ->and($listings[0]['url'])->toBe('https://acme.example')
        ->and($listings[0]['source_url'])->toBe('https://news.ycombinator.com/item?id=100');
});

it('paginates through multiple pages of comments', function () {
    fakeAlgolia(
        story: [
            'objectID' => '999',
            'title' => 'Ask HN: Who is hiring? (March 2026)',
        ],
        commentPages: [
            [
                'hits' => [[
                    'objectID' => '100',
                    'comment_text' => 'First Co | Engineer | Remote',
                ]],
                'nbPages' => 2,
            ],
            [
                'hits' => [[
                    'objectID' => '101',
                    'comment_text' => 'Second Co | Designer | Remote',
                ]],
                'nbPages' => 2,
            ],
        ],
    );

    $scraper = new HnHiringScraper;
    $listings = iterator_to_array($scraper->scrape());

    expect($listings)->toHaveCount(2)
        ->and($listings[0]['company'])->toBe('First Co')
        ->and($listings[1]['company'])->toBe('Second Co');
});

it('skips story results that are not who is hiring posts', function () {
    Http::fake(function (Request $request) {
        $url = $request->url();

        if (Str::contains($url, 'tags=story%2Cauthor_whoishiring')) {
            return Http::response([
                'hits' => [
                    ['objectID' => '500', 'title' => 'Ask HN: Who wants to be hired? (March 2026)'],
                    ['objectID' => '999', 'title' => 'Ask HN: Who is hiring? (March 2026)'],
                ],
                'nbPages' => 1,
            ]);
        }

        return Http::response([
            'hits' => [[
                'objectID' => '100',
                'comment_text' => 'Acme | Engineer',
            ]],
            'nbPages' => 1,
        ]);
    });

    $scraper = new HnHiringScraper;
    $listings = iterator_to_array($scraper->scrape());

    expect($listings)->toHaveCount(1)
        ->and($listings[0]['raw_data']['story_id'])->toBe(999);
});

it('returns empty when the story search fails', function () {
    Http::fake(fn () => Http::response('', 500));

    $scraper = new HnHiringScraper;

    expect(iterator_to_array($scraper->scrape()))->toBeEmpty();
});

it('returns empty on connection failure', function () {
    Http::fake([
        'hn.algolia.com/*' => Http::failedConnection(),
    ]);

    expect(iterator_to_array((new HnHiringScraper)->scrape()))->toBeEmpty();
});

it('returns empty when no who is hiring story is found', function () {
    fakeAlgolia(story: [], commentPages: []);

    $scraper = new HnHiringScraper;

    expect(iterator_to_array($scraper->scrape()))->toBeEmpty();
});

it('returns empty when the comment search fails', function () {
    Http::fake(function (Request $request) {
        if (Str::contains($request->url(), 'tags=story%2Cauthor_whoishiring')) {
            return Http::response([
                'hits' => [[
                    'objectID' => '999',
                    'title' => 'Ask HN: Who is hiring? (March 2026)',
                ]],
                'nbPages' => 1,
            ]);
        }

        return Http::response('', 500);
    });

    $scraper = new HnHiringScraper;

    expect(iterator_to_array($scraper->scrape()))->toBeEmpty();
});

it('skips comments that are too short or missing text', function () {
    fakeAlgolia(
        story: [
            'objectID' => '999',
            'title' => 'Ask HN: Who is hiring? (March 2026)',
        ],
        commentPages: [
            [
                'hits' => [
                    ['objectID' => '100', 'comment_text' => 'hi'],
                    ['objectID' => '101', 'comment_text' => ''],
                    ['objectID' => '102', 'comment_text' => 'Acme | Engineer | Remote'],
                ],
                'nbPages' => 1,
            ],
        ],
    );

    $scraper = new HnHiringScraper;
    $listings = iterator_to_array($scraper->scrape());

    expect($listings)->toHaveCount(1)
        ->and($listings[0]['company'])->toBe('Acme');
});

it('extracts the first non-social http link as url when present', function () {
    fakeAlgolia(
        story: ['objectID' => '999', 'title' => 'Ask HN: Who is hiring? (March 2026)'],
        commentPages: [[
            'hits' => [[
                'objectID' => '100',
                'comment_text' => 'Acme | Engineer | Remote<p>Visit <a href="https://acme.example/about">our site</a> for details.',
            ]],
            'nbPages' => 1,
        ]],
    );

    $listings = iterator_to_array((new HnHiringScraper)->scrape());

    expect($listings[0]['url'])->toBe('https://acme.example/about')
        ->and($listings[0]['source_url'])->toBe('https://news.ycombinator.com/item?id=100');
});

it('prefers anchors with apply or careers keywords over generic links', function () {
    fakeAlgolia(
        story: ['objectID' => '999', 'title' => 'Ask HN: Who is hiring? (March 2026)'],
        commentPages: [[
            'hits' => [[
                'objectID' => '100',
                'comment_text' => 'Acme | Engineer | Remote<p>About: <a href="https://acme.example">homepage</a>.<p>Apply: <a href="https://acme.example/careers">careers page</a>.',
            ]],
            'nbPages' => 1,
        ]],
    );

    $listings = iterator_to_array((new HnHiringScraper)->scrape());

    expect($listings[0]['url'])->toBe('https://acme.example/careers');
});

it('falls back to mailto when only an email is present', function () {
    fakeAlgolia(
        story: ['objectID' => '999', 'title' => 'Ask HN: Who is hiring? (March 2026)'],
        commentPages: [[
            'hits' => [[
                'objectID' => '100',
                'comment_text' => 'Acme | Engineer | Remote<p>Email jobs@acme.example to apply.',
            ]],
            'nbPages' => 1,
        ]],
    );

    $listings = iterator_to_array((new HnHiringScraper)->scrape());

    expect($listings[0]['url'])->toBe('mailto:jobs@acme.example')
        ->and($listings[0]['source_url'])->toBe('https://news.ycombinator.com/item?id=100');
});

it('falls back to the hn comment url when no links or emails exist', function () {
    fakeAlgolia(
        story: ['objectID' => '999', 'title' => 'Ask HN: Who is hiring? (March 2026)'],
        commentPages: [[
            'hits' => [[
                'objectID' => '100',
                'comment_text' => 'Acme | Engineer | Remote<p>We are hiring. Reply here for details.',
            ]],
            'nbPages' => 1,
        ]],
    );

    $listings = iterator_to_array((new HnHiringScraper)->scrape());

    expect($listings[0]['url'])->toBe('https://news.ycombinator.com/item?id=100')
        ->and($listings[0]['source_url'])->toBe('https://news.ycombinator.com/item?id=100');
});

it('skips twitter linkedin and hn self-links', function () {
    fakeAlgolia(
        story: ['objectID' => '999', 'title' => 'Ask HN: Who is hiring? (March 2026)'],
        commentPages: [[
            'hits' => [[
                'objectID' => '100',
                'comment_text' => 'Acme | Engineer | Remote'
                    .'<p>Follow us on <a href="https://twitter.com/acme">Twitter</a>'
                    .' and <a href="https://www.linkedin.com/company/acme">LinkedIn</a>.'
                    .'<p>See our <a href="https://news.ycombinator.com/user?id=acmehq">profile</a>.'
                    .'<p>Apply: <a href="https://acme.example/jobs">acme.example/jobs</a>.',
            ]],
            'nbPages' => 1,
        ]],
    );

    $listings = iterator_to_array((new HnHiringScraper)->scrape());

    expect($listings[0]['url'])->toBe('https://acme.example/jobs');
});
