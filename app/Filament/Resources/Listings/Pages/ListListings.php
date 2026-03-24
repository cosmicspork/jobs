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
                    ->whereNull('read_at')
                    ->where('relevance', Relevance::Relevant)),
            'starred' => Tab::make('Starred')
                ->icon('heroicon-o-star')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNotNull('starred_at')
                    ->orderByDesc('starred_at')),
            'shortlisted' => Tab::make('Shortlisted')
                ->icon('heroicon-o-clipboard-document-check')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNotNull('shortlisted_at')
                    ->doesntHave('applications')),
            'applied' => Tab::make('Applied')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('applications')),
            'all' => Tab::make('All'),
        ];
    }
}
