<?php

namespace App\Filament\Resources\Listings\Tables;

use App\Filament\Resources\Listings\Pages\ListListings;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\TargetProfile;
use App\Relevance;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ListingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $userId = auth()->id();

                $bestPivotId = DB::table('listing_user as inner_lu')
                    ->select('inner_lu.id')
                    ->whereColumn('inner_lu.listing_id', 'listings.id')
                    ->where('inner_lu.user_id', $userId)
                    ->orderByRaw(ListingUser::orderByRelevanceSql('inner_lu.relevance'))
                    ->orderByDesc('inner_lu.scored_at')
                    ->limit(1);

                return $query
                    ->join('listing_user', function ($join) use ($userId, $bestPivotId) {
                        $join->on('listings.id', '=', 'listing_user.listing_id')
                            ->where('listing_user.user_id', $userId)
                            ->whereRaw('listing_user.id = ('.$bestPivotId->toRawSql().')');
                    })
                    ->leftJoin('target_profiles', 'listing_user.target_profile_id', '=', 'target_profiles.id')
                    ->withCount(['applications' => fn ($q) => $q->where('user_id', $userId)])
                    ->select([
                        'listings.*',
                        'listing_user.id as pivot_id',
                        'listing_user.relevance',
                        'listing_user.score_data',
                        'listing_user.scored_at',
                        'listing_user.read_at',
                        'listing_user.starred_at',
                        'listing_user.shortlisted_at',
                        'listing_user.dismissed_at',
                        'listing_user.target_profile_id',
                        'target_profiles.name as target_name',
                    ]);
            })
            ->columns([
                IconColumn::make('starred_at')
                    ->label('')
                    ->state(fn (Listing $record): bool => (bool) $record->starred_at)
                    ->icon(fn (Listing $record): string => $record->starred_at ? 'heroicon-s-star' : 'heroicon-o-star')
                    ->color(fn (Listing $record): string => $record->starred_at ? 'warning' : 'gray')
                    ->action(function (Listing $record): void {
                        ListingUser::forUserListing(auth()->id(), $record->id)?->toggleStarred();
                    })
                    ->grow(false),
                IconColumn::make('shortlisted_at')
                    ->label('Shortlisted')
                    ->state(fn (Listing $record): bool => (bool) $record->shortlisted_at)
                    ->icon(fn (Listing $record): string => $record->shortlisted_at
                        ? 'heroicon-s-clipboard-document-check'
                        : 'heroicon-o-clipboard-document-check')
                    ->color(fn (Listing $record): string => $record->shortlisted_at ? 'success' : 'gray')
                    ->action(function (Listing $record): void {
                        ListingUser::forUserListing(auth()->id(), $record->id)?->toggleShortlisted();
                    })
                    ->visible(fn (ListListings $livewire): bool => in_array($livewire->activeTab, ['all', 'starred'])),
                IconColumn::make('applications_count')
                    ->label('Applied')
                    ->icon(fn (Listing $record): ?string => $record->applications_count > 0 ? 'heroicon-s-check-circle' : null)
                    ->color('success')
                    ->visible(fn (ListListings $livewire): bool => in_array($livewire->activeTab, ['starred', 'all'])),
                TextColumn::make('match')
                    ->label('Match')
                    ->state(fn (Listing $record): string => $record->target_name
                        ? $record->target_name.' · '.($record->relevance?->getLabel() ?? 'Unscored')
                        : ($record->relevance?->getLabel() ?? 'Unscored'))
                    ->badge()
                    ->color(fn (Listing $record) => $record->relevance?->getColor() ?? 'gray'),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight(fn (Listing $record): string => $record->read_at ? 'regular' : 'bold')
                    ->limit(50),
                TextColumn::make('company')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('board')
                    ->badge()
                    ->sortable(),
            ])
            ->defaultSort('scored_at', 'desc')
            ->emptyStateIcon(fn (ListListings $livewire): string => match ($livewire->activeTab) {
                'inbox' => 'heroicon-o-inbox',
                'starred' => 'heroicon-o-star',
                'shortlisted' => 'heroicon-o-clipboard-document-check',
                'applied' => 'heroicon-o-check-circle',
                default => 'heroicon-o-rectangle-stack',
            })
            ->emptyStateHeading(fn (ListListings $livewire): string => match ($livewire->activeTab) {
                'inbox' => "You're all caught up",
                'starred' => 'Nothing starred yet',
                'shortlisted' => 'Nothing shortlisted yet',
                'applied' => 'No applications yet',
                default => 'No listings yet',
            })
            ->emptyStateDescription(fn (ListListings $livewire): string => match ($livewire->activeTab) {
                'inbox' => 'New relevant and maybe matches will land here as they are scored.',
                'starred' => 'Star a listing to keep it handy.',
                'shortlisted' => 'Shortlist a listing to queue it for an application.',
                'applied' => 'Listings you generate an application for will appear here.',
                default => 'Listings will appear here once boards are scraped and scored.',
            })
            ->filters([
                TernaryFilter::make('remote')
                    ->label('Remote'),
                TernaryFilter::make('scored')
                    ->label('Scored')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('listing_user.scored_at'),
                        false: fn ($query) => $query->whereNull('listing_user.scored_at'),
                    ),
                TernaryFilter::make('read')
                    ->label('Read')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('listing_user.read_at'),
                        false: fn ($query) => $query->whereNull('listing_user.read_at'),
                    ),
                TernaryFilter::make('starred')
                    ->label('Starred')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('listing_user.starred_at'),
                        false: fn ($query) => $query->whereNull('listing_user.starred_at'),
                    ),
                TernaryFilter::make('dismissed')
                    ->label('Dismissed')
                    ->default(false)
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('listing_user.dismissed_at'),
                        false: fn ($query) => $query->whereNull('listing_user.dismissed_at'),
                    )
                    ->indicateUsing(fn (array $data): ?string => match ($data['value'] ?? null) {
                        true => 'Dismissed only',
                        null => 'Including dismissed',
                        default => null,
                    }),
                SelectFilter::make('board')
                    ->options(fn () => collect(config('boards'))->mapWithKeys(fn ($board, $key) => [$key => $board['name']])),
                SelectFilter::make('relevance')
                    ->options(Relevance::class)
                    ->query(fn ($query, $data) => $data['value'] ? $query->where('listing_user.relevance', $data['value']) : $query),
                SelectFilter::make('target')
                    ->label('Target')
                    ->options(fn () => auth()->user()
                        ->targetProfiles
                        ->mapWithKeys(fn (TargetProfile $t) => [$t->id => $t->name])
                        ->all())
                    ->query(fn ($query, $data) => $data['value'] ? $query->where('listing_user.target_profile_id', $data['value']) : $query),
            ])
            ->recordActions([
                Action::make('toggleRead')
                    ->label(fn (Listing $record): string => $record->read_at ? 'Mark Unread' : 'Mark Read')
                    ->icon(fn (Listing $record): string => $record->read_at ? 'heroicon-o-envelope' : 'heroicon-o-envelope-open')
                    ->action(function (Listing $record): void {
                        ListingUser::forUserListing(auth()->id(), $record->id)?->toggleRead();
                    }),
                Action::make('toggleDismissed')
                    ->label(fn (Listing $record): string => $record->dismissed_at ? 'Restore' : 'Dismiss')
                    ->icon(fn (Listing $record): string => $record->dismissed_at ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-archive-box-x-mark')
                    ->color(fn (Listing $record): string => $record->dismissed_at ? 'gray' : 'danger')
                    ->action(function (Listing $record): void {
                        ListingUser::forUserListing(auth()->id(), $record->id)?->toggleDismissed();
                    }),
                ViewAction::make(),
            ])
            ->toolbarActions([
                Action::make('markAllAsRead')
                    ->label('Mark Page as Read')
                    ->icon('heroicon-o-envelope-open')
                    ->action(function (Table $table): void {
                        ListingUser::query()
                            ->whereIn('listing_id', $table->getRecords()->pluck('id'))
                            ->where('user_id', auth()->id())
                            ->whereNull('read_at')
                            ->update(['read_at' => now()]);
                    }),
                BulkActionGroup::make([
                    BulkAction::make('dismiss')
                        ->label('Dismiss')
                        ->icon('heroicon-o-archive-box-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            ListingUser::query()
                                ->whereIn('listing_id', $records->pluck('id'))
                                ->where('user_id', auth()->id())
                                ->update(['dismissed_at' => now()]);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->recordUrl(fn (Listing $record): string => route('filament.admin.resources.listings.view', $record));
    }
}
