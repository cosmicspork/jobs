<?php

namespace App\Filament\Resources\Listings\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'applications';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('user_id', auth()->id()))
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('resume_path')
                    ->label('Resume')
                    ->placeholder('Pending')
                    ->url(fn ($record): ?string => $record->resume_path ? Storage::url($record->resume_path) : null)
                    ->openUrlInNewTab(),
                TextColumn::make('cover_letter_path')
                    ->label('Cover Letter')
                    ->placeholder('Pending')
                    ->url(fn ($record): ?string => $record->cover_letter_path ? Storage::url($record->cover_letter_path) : null)
                    ->openUrlInNewTab(),
                TextColumn::make('applied_at')
                    ->label('Applied')
                    ->since()
                    ->placeholder('Not applied'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->since(),
            ]);
    }
}
