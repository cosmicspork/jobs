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
        return 'queue';
    }

    public function getTabs(): array
    {
        return [
            'queue' => Tab::make('Queue')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNull('read_at')
                    ->where('relevance', Relevance::Relevant)),
            'relevant' => Tab::make('Relevant')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('relevance', Relevance::Relevant)),
            'maybe' => Tab::make('Maybe')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('relevance', Relevance::Maybe)),
            'applied' => Tab::make('Applied')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('applications')),
            'all' => Tab::make('All'),
        ];
    }
}
