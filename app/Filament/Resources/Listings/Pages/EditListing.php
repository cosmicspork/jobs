<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Resources\Listings\Concerns\HasListingActions;
use App\Filament\Resources\Listings\ListingResource;
use App\Models\ListingUser;
use Filament\Resources\Pages\EditRecord;

class EditListing extends EditRecord
{
    use HasListingActions;

    protected static string $resource = ListingResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['relevance'] = ListingUser::forUserListing(auth()->id(), $this->record->getKey())?->relevance?->value;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $relevance = $data['relevance'] ?? null;
        unset($data['relevance']);

        if ($relevance !== null) {
            ListingUser::forUserListing(auth()->id(), $this->record->getKey())
                ?->update([
                    'relevance' => $relevance,
                    'scored_at' => now(),
                ]);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getStartApplicationAction(),
            $this->getToggleStarredAction(),
            $this->getJobLinkAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
