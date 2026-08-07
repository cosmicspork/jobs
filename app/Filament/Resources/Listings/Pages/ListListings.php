<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Resources\Listings\ListingResource;
use App\Models\ListingUser;
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
                ->modifyQueryUsing(fn (Builder $query) => ListingUser::applyStage($query, 'inbox', 'listing_user.')),
            'starred' => Tab::make('Starred')
                ->icon('heroicon-o-star')
                ->badge($counts['starred'] ?: null)
                ->modifyQueryUsing(function (Builder $query) {
                    ListingUser::applyStage($query, 'starred', 'listing_user.');
                    $query->orderByDesc('listing_user.starred_at');
                }),
            'shortlisted' => Tab::make('Shortlisted')
                ->icon('heroicon-o-clipboard-document-check')
                ->badge($counts['shortlisted'] ?: null)
                ->modifyQueryUsing(function (Builder $query) {
                    ListingUser::applyStage($query, 'shortlisted', 'listing_user.');
                    $query->orderByDesc('listing_user.shortlisted_at');
                }),
            'applied' => Tab::make('Applied')
                ->icon('heroicon-o-check-circle')
                ->badge($counts['applied'] ?: null)
                ->modifyQueryUsing(function (Builder $query) {
                    ListingUser::applyStage($query, 'applied', 'listing_user.');
                    $query->orderByDesc('listing_user.applied_at');
                }),
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
            ->whereNull('starred_at')
            ->whereNull('shortlisted_at')
            ->whereNull('applied_at')
            ->selectRaw('listing_id')
            ->selectRaw('MIN('.ListingUser::orderByRelevanceSql().') as rank')
            ->groupBy('listing_id');

        $inbox = (int) DB::query()
            ->fromSub($bestUnreadPerListing, 'b')
            ->whereIn('rank', [0, 1])
            ->count();

        $countIn = function (string $stage) use ($userId): int {
            $query = ListingUser::query()
                ->where('user_id', $userId)
                ->whereNull('dismissed_at');

            ListingUser::applyStage($query, $stage);

            return (int) $query->distinct()->count('listing_id');
        };

        return $this->tabCounts = [
            'inbox' => $inbox,
            'starred' => $countIn('starred'),
            'shortlisted' => $countIn('shortlisted'),
            // Dismissed is excluded here as it is in every tab's query, so
            // dismissing something you applied to also hides it from history.
            'applied' => $countIn('applied'),
        ];
    }
}
