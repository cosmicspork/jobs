<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Listings\Pages\ListListings;
use App\Models\Application;
use App\Models\ListingUser;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ListingStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $userId = auth()->id();
        $today = today()->toDateString();
        $weekStart = now()->startOfWeek();

        // Unread, best-relevance relevant/maybe matches — the Inbox triage queue.
        $bestUnreadPerListing = DB::table('listing_user')
            ->where('user_id', $userId)
            ->whereNull('dismissed_at')
            ->whereNull('read_at')
            ->selectRaw('listing_id')
            ->selectRaw('MIN('.ListingUser::orderByRelevanceSql().') as rank')
            ->groupBy('listing_id');

        $inbox = (int) DB::query()
            ->fromSub($bestUnreadPerListing, 'b')
            ->whereIn('rank', [0, 1])
            ->count();

        // New listings over time, collapsed to one row per listing.
        $newPerListing = DB::table('listing_user')
            ->where('user_id', $userId)
            ->whereNull('dismissed_at')
            ->selectRaw('listing_id')
            ->selectRaw('MIN(listing_user.created_at) as first_seen')
            ->groupBy('listing_id');

        /** @var object{today: int, this_week: int} $volume */
        $volume = DB::query()
            ->fromSub($newPerListing, 'b')
            ->selectRaw('SUM(CASE WHEN DATE(first_seen) = ? THEN 1 ELSE 0 END) as today', [$today])
            ->selectRaw('SUM(CASE WHEN first_seen >= ? THEN 1 ELSE 0 END) as this_week', [$weekStart])
            ->first();

        $awaiting = (int) ListingUser::query()
            ->where('user_id', $userId)
            ->whereNull('dismissed_at')
            ->whereNotNull('shortlisted_at')
            ->whereDoesntHave('listing.applications', fn ($q) => $q->where('user_id', $userId))
            ->distinct()->count('listing_id');

        $applications = Application::where('user_id', $userId)->count();

        return [
            Stat::make('Inbox', $inbox)
                ->description('Relevant + maybe, unread')
                ->color('primary')
                ->url(ListListings::getUrl(['activeTab' => 'inbox'])),
            Stat::make('Awaiting application', $awaiting)
                ->description('Shortlisted, not yet applied')
                ->color($awaiting > 0 ? 'warning' : 'gray')
                ->url(ListListings::getUrl(['activeTab' => 'shortlisted'])),
            Stat::make('Applications', $applications)
                ->description('Resumes & cover letters generated')
                ->color('primary')
                ->url(ApplicationResource::getUrl()),
            Stat::make('New this week', (int) $volume->this_week)
                ->description("Today: {$volume->today}")
                ->color('gray')
                ->url(ListListings::getUrl(['activeTab' => 'inbox'])),
        ];
    }
}
