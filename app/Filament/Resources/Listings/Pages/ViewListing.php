<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Pages\ApplicationQuestions;
use App\Filament\Resources\Listings\Concerns\HasListingActions;
use App\Filament\Resources\Listings\ListingResource;
use App\Models\Listing;
use App\Models\ListingUser;
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

        $pivot = $this->getUserPivot();

        if ($pivot && ! $pivot->read_at) {
            $pivot->update(['read_at' => now()]);
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
                ->visible(fn (): bool => (bool) $this->getUserPivot()?->shortlisted_at === false)
                ->action(function (): void {
                    $this->getUserPivot()?->shortlist();

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
                ->label(fn (): string => $this->getUserPivot()?->read_at ? 'Mark Unread' : 'Mark Read')
                ->icon(fn (): string => $this->getUserPivot()?->read_at ? 'heroicon-o-envelope' : 'heroicon-o-envelope-open')
                ->action(function (): void {
                    $this->getUserPivot()?->toggleRead();
                }),
            $this->getJobLinkAction(),
        ];
    }

    private function getUserPivot(): ?ListingUser
    {
        return ListingUser::forUserListing(auth()->id(), $this->record->getKey());
    }
}
