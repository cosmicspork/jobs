<?php

namespace App\Filament\Resources\Applications\Tables;

use App\ApplicationStatus;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('listing.title')
                    ->label('Listing')
                    ->searchable()
                    ->wrap()
                    ->limit(60),
                TextColumn::make('listing.company')
                    ->label('Company')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('targetProfile.name')
                    ->label('Target')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('updated_at')
                    ->label('Last modified')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(ApplicationStatus::class),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->url(fn ($record): string => route('filament.admin.resources.applications.edit', $record)),
            ]);
    }
}
