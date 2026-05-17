<?php

namespace App\Filament\Resources\Listings\Concerns;

use App\Filament\Pages\ApplicationQuestions;
use App\Jobs\ScoreListing;
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

    protected function getToggleReadAction(): Action
    {
        return Action::make('toggleRead')
            ->label(fn (): string => $this->getUserPivotForAction()?->read_at ? 'Mark Unread' : 'Mark Read')
            ->icon(fn (): string => $this->getUserPivotForAction()?->read_at ? 'heroicon-o-envelope' : 'heroicon-o-envelope-open')
            ->action(function (): void {
                $this->getUserPivotForAction()?->toggleRead();
            });
    }

    protected function getToggleShortlistedAction(): Action
    {
        return Action::make('toggleShortlisted')
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
            });
    }

    protected function getToggleDismissedAction(): Action
    {
        return Action::make('toggleDismissed')
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
            });
    }

    protected function getApplicationQuestionsAction(): Action
    {
        return Action::make('applicationQuestions')
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
            });
    }

    protected function getRescoreAction(): Action
    {
        return Action::make('rescore')
            ->label('Re-score against current targets')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(fn (): bool => $this->shouldShowRescore())
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
            });
    }

    /**
     * Rescore is only worth showing when there's actionable work — either an
     * active target with no score for this listing, or a scored pivot whose
     * target was edited after the score was computed (the "Target updated
     * since" case surfaced in the target-scores view).
     */
    protected function shouldShowRescore(): bool
    {
        /** @var User $user */
        $user = auth()->user();
        /** @var Listing $listing */
        $listing = $this->record;

        $activeTargets = $user->activeTargets();

        if ($activeTargets->isEmpty()) {
            return false;
        }

        $pivots = ListingUser::query()
            ->where('listing_id', $listing->getKey())
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('target_profile_id');

        foreach ($activeTargets as $target) {
            $pivot = $pivots->get($target->id);

            if ($pivot === null || $pivot->scored_at === null) {
                return true;
            }

            if ($target->updated_at->gt($pivot->scored_at)) {
                return true;
            }
        }

        return false;
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
