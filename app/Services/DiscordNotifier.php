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

        $color = match (true) {
            $listing->score >= 80 => 0x22C55E,
            $listing->score >= 60 => 0xEAB308,
            default => 0xEF4444,
        };

        Http::post($webhookUrl, [
            'embeds' => [
                [
                    'title' => Str::limit($listing->title, 256),
                    'url' => $listing->url,
                    'color' => $color,
                    'fields' => [
                        ['name' => 'Company', 'value' => $listing->company ?? 'Unknown', 'inline' => true],
                        ['name' => 'Score', 'value' => (string) $listing->score, 'inline' => true],
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
