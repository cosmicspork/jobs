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
    /**
     * @return array<int, Action>
     */
    protected function getGenerateActions(): array
    {
        return [
            $this->buildGenerateAction(
                name: 'generateResume',
                label: 'Generate Resume',
                icon: 'heroicon-o-document-text',
                startedTitle: 'Resume generation started',
                buildBody: fn (Listing $l, TargetProfile $t): string => "Generating resume for {$l->company} ({$t->name})...",
                dispatch: fn (Listing $l, TargetProfile $t) => Application::generateResume($l, auth()->user(), $t),
            ),
            $this->buildGenerateAction(
                name: 'generateCoverLetter',
                label: 'Generate Cover Letter',
                icon: 'heroicon-o-envelope',
                startedTitle: 'Cover letter generation started',
                buildBody: fn (Listing $l, TargetProfile $t): string => "Generating cover letter for {$l->company} ({$t->name})...",
                dispatch: fn (Listing $l, TargetProfile $t) => Application::generateCoverLetter($l, auth()->user(), $t),
            ),
            $this->buildGenerateAction(
                name: 'generateBoth',
                label: 'Generate Both',
                icon: 'heroicon-o-document-duplicate',
                startedTitle: 'Application generation started',
                buildBody: fn (Listing $l, TargetProfile $t): string => "Generating resume and cover letter for {$l->company} ({$t->name})...",
                dispatch: fn (Listing $l, TargetProfile $t) => Application::generateBoth($l, auth()->user(), $t),
            ),
        ];
    }

    private function buildGenerateAction(
        string $name,
        string $label,
        string $icon,
        string $startedTitle,
        \Closure $buildBody,
        \Closure $dispatch,
    ): Action {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color('primary')
            ->schema(fn () => $this->buildTargetSelectSchema())
            ->requiresConfirmation()
            ->action(function (array $data) use ($startedTitle, $buildBody, $dispatch): void {
                /** @var Listing $listing */
                $listing = $this->record;
                $target = $this->resolveTargetForAction($data);

                if (! $target instanceof TargetProfile) {
                    Notification::make()->title('No active target')->body('Add an active target before generating an application.')->danger()->send();

                    return;
                }

                $dispatch($listing, $target);
                Notification::make()->title($startedTitle)->body($buildBody($listing, $target))->success()->send();
            });
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

    protected function getUserPivotForAction(): ?ListingUser
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
