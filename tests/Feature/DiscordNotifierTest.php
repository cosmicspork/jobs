<?php

use App\Models\Listing;
use App\Services\DiscordNotifier;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake();
    config(['services.discord.webhook_url' => 'https://discord.com/api/webhooks/test']);
});

it('sends a discord notification for a listing', function () {
    $listing = Listing::factory()->scored(85)->create();

    app(DiscordNotifier::class)->sendListing($listing);

    Http::assertSent(function ($request) use ($listing) {
        $embed = $request->data()['embeds'][0];

        return $request->url() === 'https://discord.com/api/webhooks/test'
            && $embed['fields'][0]['value'] === $listing->company
            && $embed['fields'][1]['value'] === '85';
    });
});

it('does not send when webhook url is not configured', function () {
    config(['services.discord.webhook_url' => null]);

    $listing = Listing::factory()->scored(85)->create();

    app(DiscordNotifier::class)->sendListing($listing);

    Http::assertNothingSent();
});

it('includes the listing title and job url in the embed', function () {
    $listing = Listing::factory()->scored(90)->create([
        'title' => 'Senior Laravel Dev',
        'url' => 'https://example.com/job/123',
    ]);

    app(DiscordNotifier::class)->sendListing($listing);

    Http::assertSent(function ($request) {
        $embed = $request->data()['embeds'][0];

        return $embed['title'] === 'Senior Laravel Dev'
            && $embed['url'] === 'https://example.com/job/123';
    });
});

it('includes a link to the app listing view', function () {
    $listing = Listing::factory()->scored(80)->create();

    app(DiscordNotifier::class)->sendListing($listing);

    Http::assertSent(function ($request) use ($listing) {
        $embed = $request->data()['embeds'][0];
        $appField = collect($embed['fields'])->firstWhere('name', 'App');

        return str_contains($appField['value'], route('filament.admin.resources.listings.view', $listing));
    });
});

it('uses green color for scores 80 and above', function () {
    $listing = Listing::factory()->scored(80)->create();

    app(DiscordNotifier::class)->sendListing($listing);

    Http::assertSent(fn ($request) => $request->data()['embeds'][0]['color'] === 0x22C55E);
});

it('uses yellow color for scores between 60 and 79', function () {
    $listing = Listing::factory()->scored(70)->create();

    app(DiscordNotifier::class)->sendListing($listing);

    Http::assertSent(fn ($request) => $request->data()['embeds'][0]['color'] === 0xEAB308);
});
