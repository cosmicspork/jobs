<?php

namespace App\Filament\Resources\Listings\Concerns;

use App\Models\Application;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\TargetProfile;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;

trait HasListingActions
{
    protected function getGenerateActions(): array
    {
        return [
            Action::make('generateResume')
                ->label('Generate Resume')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->schema(fn () => $this->buildTargetSelectSchema())
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    /** @var Listing $listing */
                    $listing = $this->record;
                    $target = $this->resolveTargetForAction($data);

                    if (! $target instanceof TargetProfile) {
                        Notification::make()->title('No active target')->body('Add an active target before generating an application.')->danger()->send();

                        return;
                    }

                    Application::generateResume($listing, auth()->user(), $target);
                    Notification::make()->title('Resume generation started')->body("Generating resume for {$listing->company} ({$target->name})...")->success()->send();
                }),
            Action::make('generateCoverLetter')
                ->label('Generate Cover Letter')
                ->icon('heroicon-o-envelope')
                ->color('primary')
                ->schema(fn () => $this->buildTargetSelectSchema())
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    /** @var Listing $listing */
                    $listing = $this->record;
                    $target = $this->resolveTargetForAction($data);

                    if (! $target instanceof TargetProfile) {
                        Notification::make()->title('No active target')->body('Add an active target before generating an application.')->danger()->send();

                        return;
                    }

                    Application::generateCoverLetter($listing, auth()->user(), $target);
                    Notification::make()->title('Cover letter generation started')->body("Generating cover letter for {$listing->company} ({$target->name})...")->success()->send();
                }),
            Action::make('generateBoth')
                ->label('Generate Both')
                ->icon('heroicon-o-document-duplicate')
                ->color('primary')
                ->schema(fn () => $this->buildTargetSelectSchema())
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    /** @var Listing $listing */
                    $listing = $this->record;
                    $target = $this->resolveTargetForAction($data);

                    if (! $target instanceof TargetProfile) {
                        Notification::make()->title('No active target')->body('Add an active target before generating an application.')->danger()->send();

                        return;
                    }

                    Application::generateBoth($listing, auth()->user(), $target);
                    Notification::make()->title('Application generation started')->body("Generating resume and cover letter for {$listing->company} ({$target->name})...")->success()->send();
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

    /**
     * @return array<int, Component>
     */
    private function buildTargetSelectSchema(): array
    {
        /** @var User $user */
        $user = auth()->user();
        /** @var Listing $listing */
        $listing = $this->record;

        $options = $user->activeTargets()
            ->mapWithKeys(fn (TargetProfile $t) => [$t->id => $t->name])
            ->all();

        if ($options === []) {
            return [];
        }

        return [
            Select::make('target_profile_id')
                ->label('Target')
                ->options($options)
                ->default($user->bestTargetFor($listing)?->id)
                ->required(),
        ];
    }

    private function resolveTargetForAction(array $data): ?TargetProfile
    {
        /** @var User $user */
        $user = auth()->user();
        /** @var Listing $listing */
        $listing = $this->record;

        if (! empty($data['target_profile_id'])) {
            return $user->targetProfiles()
                ->where('id', $data['target_profile_id'])
                ->where('is_active', true)
                ->first();
        }

        return $user->bestTargetFor($listing);
    }
}
