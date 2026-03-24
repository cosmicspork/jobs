<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Resources\Listings\Concerns\HasListingActions;
use App\Filament\Resources\Listings\ListingResource;
use Filament\Resources\Pages\EditRecord;

class EditListing extends EditRecord
{
    use HasListingActions;

    protected static string $resource = ListingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->getGenerateActions(),
            $this->getToggleStarredAction(),
            $this->getJobLinkAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
