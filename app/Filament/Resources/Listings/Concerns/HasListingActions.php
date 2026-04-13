<?php

namespace App\Filament\Resources\Listings\Concerns;

use App\Models\Application;
use App\Models\Listing;
use App\Models\ListingUser;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

trait HasListingActions
{
    protected function getGenerateActions(): array
    {
        return [
            Action::make('generateResume')
                ->label('Generate Resume')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var Listing $listing */
                    $listing = $this->record;
                    Application::generateResume($listing, auth()->user());
                    Notification::make()->title('Resume generation started')->body("Generating resume for {$listing->company}...")->success()->send();
                }),
            Action::make('generateCoverLetter')
                ->label('Generate Cover Letter')
                ->icon('heroicon-o-envelope')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var Listing $listing */
                    $listing = $this->record;
                    Application::generateCoverLetter($listing, auth()->user());
                    Notification::make()->title('Cover letter generation started')->body("Generating cover letter for {$listing->company}...")->success()->send();
                }),
            Action::make('generateBoth')
                ->label('Generate Both')
                ->icon('heroicon-o-document-duplicate')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var Listing $listing */
                    $listing = $this->record;
                    Application::generateBoth($listing, auth()->user());
                    Notification::make()->title('Application generation started')->body("Generating resume and cover letter for {$listing->company}...")->success()->send();
                }),
        ];
    }

    protected function getToggleStarredAction(): Action
    {
        $pivot = $this->getUserPivotForAction();

        return Action::make('toggleStarred')
            ->label($pivot?->starred_at ? 'Unstar' : 'Star')
            ->icon($pivot?->starred_at ? 'heroicon-s-star' : 'heroicon-o-star')
            ->color('warning')
            ->action(function (): void {
                $this->getUserPivotForAction()?->toggleStarred();
            });
    }

    protected function getJobLinkAction(): Action
    {
        return Action::make('openUrl')
            ->label('Job Link')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->url(function (): string {
                /** @var Listing $listing */
                $listing = $this->record;

                return $listing->url;
            })
            ->openUrlInNewTab()
            ->visible(function (): bool {
                /** @var Listing $listing */
                $listing = $this->record;

                return filled($listing->url);
            });
    }

    private function getUserPivotForAction(): ?ListingUser
    {
        return ListingUser::forUserListing(auth()->id(), $this->record->getKey());
    }
}
