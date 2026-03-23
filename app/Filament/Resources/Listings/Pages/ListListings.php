<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Resources\Listings\ListingResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListListings extends ListRecords
{
    protected static string $resource = ListingResource::class;

    public function getDefaultActiveTab(): string|int|null
    {
        return 'unread';
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(),
            'unread' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('read_at')),
            'applied' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('applications')),
        ];
    }
}
