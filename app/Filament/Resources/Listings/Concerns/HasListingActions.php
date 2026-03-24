<?php

namespace App\Filament\Resources\Listings\Concerns;

use App\Models\Application;
use App\Models\Listing;
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
                    Application::generateResume($listing);
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
                    Application::generateCoverLetter($listing);
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
                    Application::generateBoth($listing);
                    Notification::make()->title('Application generation started')->body("Generating resume and cover letter for {$listing->company}...")->success()->send();
                }),
        ];
    }

    protected function getToggleStarredAction(): Action
    {
        return Action::make('toggleStarred')
            ->label(fn (): string => $this->record->starred_at ? 'Unstar' : 'Star')
            ->icon(fn (): string => $this->record->starred_at ? 'heroicon-s-star' : 'heroicon-o-star')
            ->color('warning')
            ->action(fn () => $this->record->toggleStarred());
    }

    protected function getJobLinkAction(): Action
    {
        return Action::make('openUrl')
            ->label('Job Link')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->url(fn (): ?string => $this->record->url)
            ->openUrlInNewTab()
            ->visible(fn (): bool => filled($this->record->url));
    }
}
