<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Resources\Listings\ListingResource;
use App\Models\Application;
use App\Models\Listing;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewListing extends ViewRecord
{
    protected static string $resource = ListingResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var Listing $listing */
        $listing = $this->record;

        if (! $listing->read_at) {
            $listing->update(['read_at' => now()]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateApplication')
                ->label('Generate Application')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Generate Application')
                ->modalDescription('This will generate a tailored resume and cover letter for this listing.')
                ->action(function (): void {
                    /** @var Listing $listing */
                    $listing = $this->record;

                    Application::generate($listing);

                    Notification::make()
                        ->title('Application generation started')
                        ->body("Generating resume and cover letter for {$listing->company}...")
                        ->success()
                        ->send();
                }),
            Action::make('toggleRead')
                ->label(fn (): string => $this->record->read_at ? 'Mark Unread' : 'Mark Read')
                ->icon(fn (): string => $this->record->read_at ? 'heroicon-o-envelope' : 'heroicon-o-envelope-open')
                ->action(fn () => $this->record->toggleRead()),
            Action::make('openUrl')
                ->label('View Original')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): ?string => $this->record->url)
                ->openUrlInNewTab()
                ->visible(fn (): bool => filled($this->record->url)),
        ];
    }
}
