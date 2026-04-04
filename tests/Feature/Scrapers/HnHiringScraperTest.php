<?php

use App\Services\Scrapers\HnHiringScraper;
use Illuminate\Support\Facades\Http;

it('parses hn jobs html into listings', function () {
    Http::fake([
        'nchelluri.github.io/hnjobs/' => Http::response(<<<'HTML'
<!doctype html>
<html lang="en">
<head><title>Ask HN: Who is hiring? (March 2026)</title></head>
<body>
  <div class="comments">
    <div class="content  remote " id="comment_100">
      <div class="close">&times;</div>
      <strong>by <a href="https://news.ycombinator.com/user?id=someone">someone</a></strong>
      <small><a href="https://news.ycombinator.com/item?id=100">Original Post</a> 02 Mar 26 17:07 UTC</small>
      <br/><br/>
      Acme Corp | Senior PHP Developer | Remote | $150k-$200k<p>We are hiring a senior PHP developer to work on our platform.
    </div>
    <div class="content " id="comment_101">
      <div class="close">&times;</div>
      <strong>by <a href="https://news.ycombinator.com/user?id=other">other</a></strong>
      <small><a href="https://news.ycombinator.com/item?id=101">Original Post</a> 02 Mar 26 16:44 UTC</small>
      <br/><br/>
      BigCo | Frontend Engineer | NYC | $120k-$160k<p>Looking for a frontend engineer in our NYC office.
    </div>
  </div>
</body>
</html>
HTML),
    ]);

    $scraper = new HnHiringScraper;
    $listings = iterator_to_array($scraper->scrape());

    expect($listings)->toHaveCount(2)
        ->and($listings[0]['company'])->toBe('Acme Corp')
        ->and($listings[0]['remote'])->toBeTrue()
        ->and($listings[0]['salary_min'])->toBe(150000)
        ->and($listings[0]['salary_max'])->toBe(200000)
        ->and($listings[0]['url'])->toContain('item?id=100')
        ->and($listings[1]['company'])->toBe('BigCo')
        ->and($listings[1]['remote'])->toBeFalse();
});

it('skips reply comments with margin-left style', function () {
    Http::fake([
        'nchelluri.github.io/hnjobs/' => Http::response(<<<'HTML'
<!doctype html>
<html lang="en">
<head><title>Ask HN: Who is hiring? (March 2026)</title></head>
<body>
  <div class="comments">
    <div class="content  remote " id="comment_100">
      <div class="close">&times;</div>
      <strong>by <a href="https://news.ycombinator.com/user?id=someone">someone</a></strong>
      <small><a href="https://news.ycombinator.com/item?id=100">Original Post</a> 02 Mar 26 17:07 UTC</small>
      <br/><br/>
      Acme Corp | Senior PHP Developer | Remote | $150k-$200k<p>Hiring now.
    </div>
    <div class="content " style="margin-left: 20px" id="comment_200">
      <div class="close">&times;</div>
      <strong>by <a href="https://news.ycombinator.com/user?id=replier">replier</a></strong>
      <small><a href="https://news.ycombinator.com/item?id=200">Original Post</a> 03 Mar 26 10:00 UTC</small>
      <br/><br/>
      This sounds like a great opportunity!
    </div>
  </div>
</body>
</html>
HTML),
    ]);

    $scraper = new HnHiringScraper;
    $listings = iterator_to_array($scraper->scrape());

    expect($listings)->toHaveCount(1)
        ->and($listings[0]['company'])->toBe('Acme Corp');
});

it('returns empty array on failed request', function () {
    Http::fake([
        'nchelluri.github.io/hnjobs/' => Http::response('', 500),
    ]);

    $scraper = new HnHiringScraper;

    expect(iterator_to_array($scraper->scrape()))->toBeEmpty();
});

it('returns empty array when no comments found', function () {
    Http::fake([
        'nchelluri.github.io/hnjobs/' => Http::response(<<<'HTML'
<!doctype html>
<html lang="en">
<head><title>Ask HN: Who is hiring?</title></head>
<body><div class="comments"></div></body>
</html>
HTML),
    ]);

    $scraper = new HnHiringScraper;

    expect(iterator_to_array($scraper->scrape()))->toBeEmpty();
});
