<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Resources\Listings\ListingResource;
use App\Relevance;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListListings extends ListRecords
{
    protected static string $resource = ListingResource::class;

    public function getDefaultActiveTab(): string|int|null
    {
        return 'new';
    }

    public function getTabs(): array
    {
        return [
            'new' => Tab::make('New')
                ->icon('heroicon-o-sparkles')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNull('listing_user.read_at')
                    ->where('listing_user.relevance', Relevance::Relevant)),
            'starred' => Tab::make('Starred')
                ->icon('heroicon-o-star')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNotNull('listing_user.starred_at')
                    ->orderByDesc('listing_user.starred_at')),
            'shortlisted' => Tab::make('Shortlisted')
                ->icon('heroicon-o-clipboard-document-check')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNotNull('listing_user.shortlisted_at')
                    ->whereDoesntHave('applications', fn ($q) => $q->where('user_id', auth()->id()))),
            'applied' => Tab::make('Applied')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereHas('applications', fn ($q) => $q->where('user_id', auth()->id()))),
            'all' => Tab::make('All'),
        ];
    }
}
