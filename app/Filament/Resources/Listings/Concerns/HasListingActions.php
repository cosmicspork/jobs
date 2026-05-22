<?php

namespace App\Filament\Resources\Listings\Concerns;

use App\ApplicationStatus;
use App\Jobs\ScoreListing;
use App\Models\Application;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\TargetProfile;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;

trait HasListingActions
{
    /**
     * Replaces the legacy three-button Generate/Cover/Both split. Asks for
     * target + which artifacts to draft + optional extra-instructions in one
     * modal, creates the Application, dispatches generation, and redirects
     * to the workspace.
     */
    protected function getStartApplicationAction(): Action
    {
        return Action::make('startApplication')
            ->label('Start application')
            ->icon('heroicon-o-rocket-launch')
            ->color('primary')
            ->schema(function () {
                /** @var Listing $listing */
                $listing = $this->record;

                return array_merge(
                    $this->buildTargetSelectSchema(),
                    [
                        CheckboxList::make('artifacts')
                            ->label('What to draft now?')
                            ->options([
                                'resume' => 'Resume',
                                'cover_letter' => 'Cover letter',
                            ])
                            ->default(['resume', 'cover_letter'])
                            ->columns(2)
                            ->bulkToggleable()
                            ->helperText('Leave both unchecked to just create an empty workspace.'),
                        Textarea::make('extra_instructions')
                            ->label('Anything else the AI should know?')
                            ->placeholder('Optional. e.g. "emphasize the queue-layer work" or "tone less formal"')
                            ->rows(3)
                            ->default(function () use ($listing): ?string {
                                $existing = Application::query()
                                    ->where('user_id', auth()->id())
                                    ->where('listing_id', $listing->id)
                                    ->latest()
                                    ->value('extra_instructions');

                                return $existing;
                            }),
                    ]
                );
            })
            ->action(function (array $data) {
                /** @var Listing $listing */
                $listing = $this->record;
                $target = $this->resolveTargetForAction($data);

                if (! $target instanceof TargetProfile) {
                    Notification::make()
                        ->title('No active target')
                        ->body('Add an active target before starting an application.')
                        ->danger()
                        ->send();

                    return;
                }

                $artifacts = $data['artifacts'] ?? [];
                $extra = trim((string) ($data['extra_instructions'] ?? '')) ?: null;

                $application = match (true) {
                    in_array('resume', $artifacts, true) && in_array('cover_letter', $artifacts, true) => Application::generateBoth($listing, auth()->user(), $target, $extra),
                    in_array('resume', $artifacts, true) => Application::generateResume($listing, auth()->user(), $target, $extra),
                    in_array('cover_letter', $artifacts, true) => Application::generateCoverLetter($listing, auth()->user(), $target, $extra),
                    default => Application::firstOrCreate(
                        [
                            'listing_id' => $listing->id,
                            'user_id' => auth()->id(),
                            'target_profile_id' => $target->id,
                        ],
                        [
                            'status' => ApplicationStatus::Ready,
                            'extra_instructions' => $extra,
                        ],
                    ),
                };

                Notification::make()
                    ->title('Application started')
                    ->body("Workspace ready for {$listing->company} ({$target->name}).")
                    ->success()
                    ->send();

                return redirect(route('filament.admin.resources.applications.edit', $application));
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
