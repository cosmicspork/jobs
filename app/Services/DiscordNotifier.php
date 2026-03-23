<?php

namespace App\Services;

use App\Models\Listing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DiscordNotifier
{
    public function sendListing(Listing $listing): void
    {
        $webhookUrl = config('services.discord.webhook_url');

        if (! $webhookUrl) {
            return;
        }

        $viewUrl = route('filament.admin.resources.listings.view', $listing);

        Http::post($webhookUrl, [
            'embeds' => [
                [
                    'title' => Str::limit($listing->title, 256),
                    'url' => $listing->url,
                    'color' => 0x22C55E,
                    'fields' => [
                        ['name' => 'Company', 'value' => $listing->company ?? 'Unknown', 'inline' => true],
                        ['name' => 'Board', 'value' => $listing->board ?? 'Unknown', 'inline' => true],
                        ['name' => 'Description', 'value' => Str::limit($listing->description, 300)],
                        ['name' => 'App', 'value' => "[View listing]({$viewUrl})"],
                    ],
                    'timestamp' => now()->toIso8601String(),
                ],
            ],
        ]);
    }
}
