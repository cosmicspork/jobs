<?php

namespace App\Filament\Resources\Listings\Tables;

use App\Filament\Resources\Listings\Pages\ListListings;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\TargetProfile;
use App\Relevance;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
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
                    ->orderByRaw("CASE inner_lu.relevance WHEN 'relevant' THEN 0 WHEN 'maybe' THEN 1 WHEN 'irrelevant' THEN 2 ELSE 99 END")
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
                    ->icon(fn (Listing $record): ?string => $record->shortlisted_at ? 'heroicon-s-clipboard-document-check' : null)
                    ->color('success')
                    ->visible(fn (ListListings $livewire): bool => in_array($livewire->activeTab, ['all'])),
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
                    ->color(fn (Listing $record) => $record->relevance?->getColor() ?? 'gray')
                    ->visible(fn (ListListings $livewire): bool => $livewire->activeTab === 'all'),
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
                    DeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(fn (Listing $record): string => route('filament.admin.resources.listings.view', $record));
    }
}
