<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Http\Response;

class FeedController extends Controller
{
    public function __invoke(): Response
    {
        $listings = Listing::query()
            ->where('score', '>=', 70)
            ->where('scored_at', '>=', now()->subDays(7))
            ->orderByDesc('score')
            ->get();

        return response()
            ->view('feed.atom', compact('listings'))
            ->header('Content-Type', 'application/atom+xml');
    }
}
