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
                IconColumn::make('applied')
                    ->label('Applied')
                    ->state(fn (Listing $record): bool => $record->applications_count > 0)
                    ->boolean()
                    ->falseIcon(null),
                TextColumn::make('relevance')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn (?Relevance $state): string => $state?->label() ?? 'Unscored')
                    ->color(fn (?Relevance $state): string => $state?->color() ?? 'gray')
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
                SelectFilter::make('board')
                    ->options(fn () => Listing::query()->distinct()->pluck('board', 'board')->filter()->all()),
                SelectFilter::make('relevance')
                    ->options(Relevance::class),
            ])
            ->recordActions([
                Action::make('toggleRead')
                    ->label(fn (Listing $record): string => $record->read_at ? 'Mark Unread' : 'Mark Read')
                    ->icon(fn (Listing $record): string => $record->read_at ? 'heroicon-o-envelope' : 'heroicon-o-envelope-open')
                    ->action(function (Listing $record): void {
                        $record->update(['read_at' => $record->read_at ? null : now()]);
                    }),
                ViewAction::make(),
            ])
            ->toolbarActions([
                Action::make('markAllAsRead')
                    ->label('Mark Page as Read')
                    ->icon('heroicon-o-envelope-open')
                    ->action(function (Table $table): void {
                        $table->getRecords()->each(function (Listing $record): void {
                            if (! $record->read_at) {
                                $record->update(['read_at' => now()]);
                            }
                        });
                    }),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(fn (Listing $record): string => route('filament.admin.resources.listings.view', $record));
    }
}
