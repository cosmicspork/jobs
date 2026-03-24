<?php

namespace App\Filament\Resources\Listings\Tables;

use App\Filament\Resources\Listings\Pages\ListListings;
use App\Models\Listing;
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

class ListingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('applications'))
            ->columns([
                IconColumn::make('starred_at')
                    ->label('')
                    ->state(fn (Listing $record): bool => (bool) $record->starred_at)
                    ->icon(fn (Listing $record): string => $record->starred_at ? 'heroicon-s-star' : 'heroicon-o-star')
                    ->color(fn (Listing $record): string => $record->starred_at ? 'warning' : 'gray')
                    ->action(fn (Listing $record) => $record->toggleStarred())
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
                TextColumn::make('relevance')
                    ->sortable()
                    ->badge()
                    ->placeholder('Unscored')
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
                        true: fn ($query) => $query->whereNotNull('scored_at'),
                        false: fn ($query) => $query->whereNull('scored_at'),
                    ),
                TernaryFilter::make('read')
                    ->label('Read')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('read_at'),
                        false: fn ($query) => $query->whereNull('read_at'),
                    ),
                TernaryFilter::make('starred')
                    ->label('Starred')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('starred_at'),
                        false: fn ($query) => $query->whereNull('starred_at'),
                    ),
                SelectFilter::make('board')
                    ->options(fn () => collect(config('boards'))->mapWithKeys(fn ($board, $key) => [$key => $board['name']])),
                SelectFilter::make('relevance')
                    ->options(Relevance::class),
            ])
            ->recordActions([
                Action::make('toggleRead')
                    ->label(fn (Listing $record): string => $record->read_at ? 'Mark Unread' : 'Mark Read')
                    ->icon(fn (Listing $record): string => $record->read_at ? 'heroicon-o-envelope' : 'heroicon-o-envelope-open')
                    ->action(fn (Listing $record) => $record->toggleRead()),
                ViewAction::make(),
            ])
            ->toolbarActions([
                Action::make('markAllAsRead')
                    ->label('Mark Page as Read')
                    ->icon('heroicon-o-envelope-open')
                    ->action(function (Table $table): void {
                        Listing::query()
                            ->whereIn('id', $table->getRecords()->pluck('id'))
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
