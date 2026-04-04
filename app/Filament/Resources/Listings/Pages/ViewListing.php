<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Pages\ApplicationQuestions;
use App\Filament\Resources\Listings\Concerns\HasListingActions;
use App\Filament\Resources\Listings\ListingResource;
use App\Models\Listing;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewListing extends ViewRecord
{
    use HasListingActions;

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
            ...$this->getGenerateActions(),
            Action::make('shortlist')
                ->label('Shortlist')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('success')
                ->visible(function (): bool {
                    /** @var Listing $listing */
                    $listing = $this->record;

                    return ! $listing->shortlisted_at;
                })
                ->action(function (): void {
                    /** @var Listing $listing */
                    $listing = $this->record;
                    $listing->shortlist();

                    Notification::make()
                        ->title('Listing shortlisted')
                        ->success()
                        ->send();
                }),
            Action::make('applicationQuestions')
                ->label('Application Questions')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->url(function (): string {
                    /** @var Listing $listing */
                    $listing = $this->record;

                    return ApplicationQuestions::getUrl(['listing' => $listing->id]);
                }),
            $this->getToggleStarredAction(),
            EditAction::make(),
            Action::make('toggleRead')
                ->label(function (): string {
                    /** @var Listing $listing */
                    $listing = $this->record;

                    return $listing->read_at ? 'Mark Unread' : 'Mark Read';
                })
                ->icon(function (): string {
                    /** @var Listing $listing */
                    $listing = $this->record;

                    return $listing->read_at ? 'heroicon-o-envelope' : 'heroicon-o-envelope-open';
                })
                ->action(function (): void {
                    /** @var Listing $listing */
                    $listing = $this->record;
                    $listing->toggleRead();
                }),
            $this->getJobLinkAction(),
        ];
    }
}
