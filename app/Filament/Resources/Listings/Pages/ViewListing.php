<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Resources\Listings\Concerns\HasListingActions;
use App\Filament\Resources\Listings\ListingResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewListing extends ViewRecord
{
    use HasListingActions;

    protected static string $resource = ListingResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $pivot = $this->getUserPivotForAction();

        if ($pivot && ! $pivot->read_at) {
            $pivot->update(['read_at' => now()]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    public function jobDetailsActions(): array
    {
        return [
            $this->getJobLinkAction(),
            ActionGroup::make([
                EditAction::make(),
                $this->getToggleReadAction(),
                $this->getToggleStarredAction(),
                $this->getToggleShortlistedAction(),
                $this->getToggleDismissedAction(),
            ]),
        ];
    }

    /**
     * @return array<int, Action>
     */
    public function matchActions(): array
    {
        return [
            $this->getRescoreAction(),
        ];
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    public function applicationActions(): array
    {
        return [
            ActionGroup::make($this->getGenerateActions())
                ->label('Generate')
                ->icon('heroicon-m-document-duplicate')
                ->color('primary')
                ->button(),
            $this->getApplicationQuestionsAction(),
        ];
    }
}
