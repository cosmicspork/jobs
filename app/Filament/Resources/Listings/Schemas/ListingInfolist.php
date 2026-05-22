<?php

namespace App\Filament\Resources\Listings\Schemas;

use App\Filament\Resources\Listings\Pages\ViewListing;
use App\Models\Application;
use App\Models\Listing;
use App\Models\ListingUser;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ListingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Job Details')
                    ->key('jobDetails')
                    ->collapsible()
                    ->afterHeader(fn (ViewListing $livewire): array => $livewire->jobDetailsActions())
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('company')
                                ->icon(Heroicon::OutlinedBuildingOffice2),
                            TextEntry::make('board')
                                ->badge(),
                            TextEntry::make('remote')
                                ->state(fn (Listing $record): string => $record->remote ? 'Remote' : 'On-site')
                                ->badge()
                                ->color(fn (Listing $record): string => $record->remote ? 'success' : 'gray'),
                        ]),
                        Grid::make(2)->schema([
                            TextEntry::make('salary_min')
                                ->label('Salary Min')
                                ->money('usd', divideBy: 1)
                                ->placeholder('—'),
                            TextEntry::make('salary_max')
                                ->label('Salary Max')
                                ->money('usd', divideBy: 1)
                                ->placeholder('—'),
                        ]),
                        TextEntry::make('url')
                            ->label('Job URL')
                            ->url(fn (?string $state): ?string => $state)
                            ->openUrlInNewTab(),
                        TextEntry::make('source_url')
                            ->label('Source')
                            ->url(fn (?string $state): ?string => $state)
                            ->openUrlInNewTab()
                            ->visible(fn (Listing $record): bool => filled($record->source_url) && $record->source_url !== $record->url),
                        TextEntry::make('description')
                            ->html()
                            ->formatStateUsing(fn (?string $state): string => $state
                                ? Str::markdown($state)
                                : '')
                            ->placeholder('No description'),
                        Grid::make(4)->schema([
                            TextEntry::make('scraped_at')
                                ->label('Scraped')
                                ->since()
                                ->color('gray'),
                            TextEntry::make('pivot_read_at')
                                ->label('Read')
                                ->since()
                                ->placeholder('Unread')
                                ->color('gray')
                                ->state(fn (Listing $record) => self::getBestPivot($record)?->read_at),
                            TextEntry::make('pivot_starred_at')
                                ->label('Starred')
                                ->since()
                                ->placeholder('Not starred')
                                ->color('gray')
                                ->state(fn (Listing $record) => self::getBestPivot($record)?->starred_at),
                            TextEntry::make('pivot_shortlisted_at')
                                ->label('Shortlisted')
                                ->since()
                                ->placeholder('Not shortlisted')
                                ->color('gray')
                                ->state(fn (Listing $record) => self::getBestPivot($record)?->shortlisted_at),
                        ]),
                    ]),
                Section::make('Match by target')
                    ->key('match')
                    ->description('How this listing scored against each of your active targets.')
                    ->collapsible()
                    ->collapsed()
                    ->afterHeader(fn (ViewListing $livewire): array => $livewire->matchActions())
                    ->schema([
                        RepeatableEntry::make('scoredTargets')
                            ->hiddenLabel()
                            ->state(fn (Listing $record): array => self::getPivotsForRecord($record)->all())
                            ->grid(2)
                            ->columns(3)
                            ->schema([
                                TextEntry::make('targetProfile.name')
                                    ->label('Target')
                                    ->weight(FontWeight::SemiBold),
                                TextEntry::make('relevance')
                                    ->badge()
                                    ->placeholder('Unscored'),
                                TextEntry::make('scored_at')
                                    ->label('Scored')
                                    ->since()
                                    ->placeholder('Never')
                                    ->color('gray'),
                                TextEntry::make('target_updated_since')
                                    ->hiddenLabel()
                                    ->state(fn (ListingUser $pivot): ?string => $pivot->scored_at
                                        && $pivot->targetProfile?->updated_at->gt($pivot->scored_at)
                                            ? 'Target updated since — re-score recommended'
                                            : null)
                                    ->badge()
                                    ->color('warning')
                                    ->icon(Heroicon::OutlinedExclamationTriangle)
                                    ->hidden(fn (?string $state): bool => blank($state))
                                    ->columnSpanFull(),
                                TextEntry::make('reasoning')
                                    ->state(fn (ListingUser $pivot): ?string => $pivot->score_data['reasoning'] ?? null)
                                    ->hidden(fn (?string $state): bool => blank($state))
                                    ->columnSpanFull(),
                                TextEntry::make('matched')
                                    ->label('Matched')
                                    ->state(fn (ListingUser $pivot): string => implode(', ', $pivot->score_data['matched_skills'] ?? []))
                                    ->hidden(fn (?string $state): bool => blank($state))
                                    ->columnSpanFull(),
                                TextEntry::make('gaps')
                                    ->label('Gaps')
                                    ->state(fn (ListingUser $pivot): string => implode(', ', $pivot->score_data['gaps'] ?? []))
                                    ->hidden(fn (?string $state): bool => blank($state))
                                    ->columnSpanFull(),
                                TextEntry::make('quality')
                                    ->label('Quality signals')
                                    ->state(fn (ListingUser $pivot): string => implode(', ', $pivot->score_data['posting_quality_signals'] ?? []))
                                    ->hidden(fn (?string $state): bool => blank($state))
                                    ->columnSpanFull(),
                                TextEntry::make('filter_reason')
                                    ->label('Filtered')
                                    ->state(fn (ListingUser $pivot): ?string => ($pivot->score_data['filtered'] ?? false)
                                        ? ($pivot->score_data['filter_reason'] ?? 'unknown')
                                        : null)
                                    ->hidden(fn (?string $state): bool => blank($state))
                                    ->columnSpanFull(),
                            ]),
                        TextEntry::make('noTargets')
                            ->hiddenLabel()
                            ->state('No active targets — add one in your profile to start scoring.')
                            ->visible(fn (Listing $record): bool => self::getPivotsForRecord($record)->isEmpty())
                            ->color('gray'),
                    ]),
                Section::make('Application')
                    ->key('application')
                    ->description('Generated resume, cover letter, and recorded application questions per target.')
                    ->collapsible()
                    ->afterHeader(fn (ViewListing $livewire): array => $livewire->applicationActions())
                    ->schema([
                        RepeatableEntry::make('listingApplications')
                            ->hiddenLabel()
                            ->state(fn (Listing $record): array => self::getApplicationsForRecord($record)->all())
                            ->grid(2)
                            ->columns(3)
                            ->schema([
                                TextEntry::make('targetProfile.name')
                                    ->label('Target')
                                    ->weight(FontWeight::SemiBold)
                                    ->placeholder('Unknown target'),
                                TextEntry::make('status')
                                    ->badge(),
                                TextEntry::make('applied_at')
                                    ->label('Applied')
                                    ->since()
                                    ->placeholder('Not applied')
                                    ->color('gray'),
                                Actions::make([
                                    Action::make('openWorkspace')
                                        ->label('Open application →')
                                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                                        ->color('primary')
                                        ->url(fn (Application $application): string => route('filament.admin.resources.applications.edit', $application)),
                                ])
                                    ->columnSpanFull(),
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->since()
                                    ->color('gray')
                                    ->columnSpanFull(),
                            ]),
                        TextEntry::make('noApplications')
                            ->hiddenLabel()
                            ->state('No applications generated yet — use the buttons above to start one.')
                            ->visible(fn (Listing $record): bool => self::getApplicationsForRecord($record)->isEmpty())
                            ->color('gray'),
                    ]),
            ]);
    }

    private static function getBestPivot(Listing $record): ?ListingUser
    {
        return ListingUser::forUserListing(auth()->id(), $record->id);
    }

    /**
     * @return Collection<int, ListingUser>
     */
    private static function getPivotsForRecord(Listing $record): Collection
    {
        return ListingUser::query()
            ->where('listing_id', $record->id)
            ->where('user_id', auth()->id())
            ->with('targetProfile')
            ->get()
            ->filter(fn (ListingUser $p) => $p->targetProfile?->is_active)
            ->sortBy(fn (ListingUser $p) => $p->targetProfile->sort_order)
            ->values();
    }

    /**
     * @return Collection<int, Application>
     */
    private static function getApplicationsForRecord(Listing $record): Collection
    {
        return Application::query()
            ->where('listing_id', $record->id)
            ->where('user_id', auth()->id())
            ->with('targetProfile')
            ->latest()
            ->get();
    }
}
