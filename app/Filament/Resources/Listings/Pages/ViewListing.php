<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Pages\ApplicationQuestions;
use App\Filament\Resources\Listings\Concerns\HasListingActions;
use App\Filament\Resources\Listings\ListingResource;
use App\Jobs\ScoreListing;
use App\Models\Application;
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

        $pivot = $this->getUserPivotForAction();

        if ($pivot && ! $pivot->read_at) {
            $pivot->update(['read_at' => now()]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->getGenerateActions(),
            Action::make('toggleShortlisted')
                ->label(fn (): string => $this->getUserPivotForAction()?->shortlisted_at ? 'Un-shortlist' : 'Shortlist')
                ->icon('heroicon-o-clipboard-document-check')
                ->color(fn (): string => $this->getUserPivotForAction()?->shortlisted_at ? 'gray' : 'success')
                ->action(function (): void {
                    $pivot = $this->getUserPivotForAction();
                    $pivot?->toggleShortlisted();

                    Notification::make()
                        ->title($pivot?->fresh()?->shortlisted_at ? 'Listing shortlisted' : 'Removed from shortlist')
                        ->success()
                        ->send();
                }),
            Action::make('toggleDismissed')
                ->label(fn (): string => $this->getUserPivotForAction()?->dismissed_at ? 'Restore' : 'Dismiss')
                ->icon(fn (): string => $this->getUserPivotForAction()?->dismissed_at ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-archive-box-x-mark')
                ->color(fn (): string => $this->getUserPivotForAction()?->dismissed_at ? 'gray' : 'danger')
                ->requiresConfirmation(fn (): bool => ! $this->getUserPivotForAction()?->dismissed_at)
                ->action(function (): void {
                    $pivot = $this->getUserPivotForAction();
                    $pivot?->toggleDismissed();

                    Notification::make()
                        ->title($pivot?->fresh()?->dismissed_at ? 'Listing dismissed' : 'Listing restored')
                        ->success()
                        ->send();
                }),
            Action::make('applicationQuestions')
                ->label('Application Questions')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->visible(fn (): bool => Application::query()
                    ->where('user_id', auth()->id())
                    ->where('listing_id', $this->record->getKey())
                    ->exists())
                ->url(function (): string {
                    /** @var Listing $listing */
                    $listing = $this->record;

                    return ApplicationQuestions::getUrl(['listing' => $listing->id]);
                }),
            $this->getToggleStarredAction(),
            Action::make('rescore')
                ->label('Re-score against current targets')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Re-runs the AI scorer for this listing against each of your active targets. The new scores update in place and will not appear in your daily digest.')
                ->action(function (): void {
                    /** @var Listing $listing */
                    $listing = $this->record;

                    $pivots = ListingUser::query()
                        ->where('listing_id', $listing->id)
                        ->where('user_id', auth()->id())
                        ->with('targetProfile')
                        ->get()
                        ->filter(fn (ListingUser $p) => $p->targetProfile?->is_active);

                    foreach ($pivots as $pivot) {
                        ScoreListing::dispatch($listing, $pivot->targetProfile);
                        if ($pivot->digested_at === null) {
                            $pivot->update(['digested_at' => now()]);
                        }
                    }

                    Notification::make()
                        ->title($pivots->isEmpty()
                            ? 'No active targets to score against'
                            : "Re-scoring against {$pivots->count()} target(s)")
                        ->success()
                        ->send();
                }),
            EditAction::make(),
            Action::make('toggleRead')
                ->label(fn (): string => $this->getUserPivotForAction()?->read_at ? 'Mark Unread' : 'Mark Read')
                ->icon(fn (): string => $this->getUserPivotForAction()?->read_at ? 'heroicon-o-envelope' : 'heroicon-o-envelope-open')
                ->action(function (): void {
                    $this->getUserPivotForAction()?->toggleRead();
                }),
            $this->getJobLinkAction(),
        ];
    }
}
