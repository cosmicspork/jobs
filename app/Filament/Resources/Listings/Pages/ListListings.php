<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Resources\Listings\ListingResource;
use App\Models\Application;
use App\Models\ListingUser;
use App\Relevance;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListListings extends ListRecords
{
    protected static string $resource = ListingResource::class;

    /** @var array<string, int>|null */
    private ?array $tabCounts = null;

    public function getDefaultActiveTab(): string|int|null
    {
        return 'inbox';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add Listing')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        $counts = $this->tabCounts();

        return [
            'inbox' => Tab::make('Inbox')
                ->icon('heroicon-o-inbox')
                ->badge($counts['inbox'] ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNull('listing_user.read_at')
                    ->whereIn('listing_user.relevance', [Relevance::Relevant, Relevance::Maybe])),
            'starred' => Tab::make('Starred')
                ->icon('heroicon-o-star')
                ->badge($counts['starred'] ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNotNull('listing_user.starred_at')
                    ->orderByDesc('listing_user.starred_at')),
            'shortlisted' => Tab::make('Shortlisted')
                ->icon('heroicon-o-clipboard-document-check')
                ->badge($counts['shortlisted'] ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNotNull('listing_user.shortlisted_at')
                    ->whereDoesntHave('applications', fn ($q) => $q->where('user_id', auth()->id()))),
            'applied' => Tab::make('Applied')
                ->icon('heroicon-o-check-circle')
                ->badge($counts['applied'] ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereHas('applications', fn ($q) => $q->where('user_id', auth()->id()))
                    ->orderByRaw('(select max(applied_at) from applications where applications.listing_id = listings.id and applications.user_id = ?) desc', [auth()->id()])),
            'all' => Tab::make('All'),
        ];
    }

    /**
     * Per-user tab counts, collapsed to one row per listing and excluding
     * dismissed listings, so each badge matches its tab's visible rows.
     *
     * @return array{inbox: int, starred: int, shortlisted: int, applied: int}
     */
    private function tabCounts(): array
    {
        if ($this->tabCounts !== null) {
            return $this->tabCounts;
        }

        $userId = auth()->id();

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

        $base = fn () => ListingUser::query()
            ->where('user_id', $userId)
            ->whereNull('dismissed_at');

        return $this->tabCounts = [
            'inbox' => $inbox,
            'starred' => (int) $base()->whereNotNull('starred_at')->distinct()->count('listing_id'),
            'shortlisted' => (int) $base()
                ->whereNotNull('shortlisted_at')
                ->whereDoesntHave('listing.applications', fn ($q) => $q->where('user_id', $userId))
                ->distinct()->count('listing_id'),
            'applied' => (int) Application::where('user_id', $userId)->distinct()->count('listing_id'),
        ];
    }
}
