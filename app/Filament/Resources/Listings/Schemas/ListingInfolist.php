<?php

namespace App\Filament\Resources\Listings\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ListingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Job Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('title')
                            ->columnSpanFull(),
                        TextEntry::make('company'),
                        TextEntry::make('board')
                            ->badge(),
                        IconEntry::make('remote')
                            ->boolean(),
                        TextEntry::make('salary_min')
                            ->label('Salary Min')
                            ->money('usd', divideBy: 1)
                            ->placeholder('-'),
                        TextEntry::make('salary_max')
                            ->label('Salary Max')
                            ->money('usd', divideBy: 1)
                            ->placeholder('-'),
                        TextEntry::make('url')
                            ->label('Job URL')
                            ->url(fn (?string $state): ?string => $state)
                            ->openUrlInNewTab(),
                        TextEntry::make('description')
                            ->columnSpanFull()
                            ->html()
                            ->formatStateUsing(fn (?string $state): string => nl2br(e($state ?? '')))
                            ->placeholder('No description'),
                    ]),
                Section::make('Score')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('score')
                            ->badge()
                            ->color(fn (?int $state): string => match (true) {
                                $state === null => 'gray',
                                $state >= 80 => 'success',
                                $state >= 60 => 'warning',
                                default => 'danger',
                            }),
                        TextEntry::make('scored_at')
                            ->since()
                            ->placeholder('Not scored'),
                        TextEntry::make('score_data.matched_skills')
                            ->label('Matched Skills')
                            ->badge()
                            ->color('success')
                            ->placeholder('None'),
                        TextEntry::make('score_data.gaps')
                            ->label('Gaps')
                            ->badge()
                            ->color('danger')
                            ->placeholder('None'),
                        TextEntry::make('score_data.reasoning')
                            ->label('Reasoning')
                            ->columnSpanFull()
                            ->placeholder('No reasoning'),
                    ]),
                Section::make('Timestamps')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('scraped_at')
                            ->since(),
                        TextEntry::make('read_at')
                            ->since()
                            ->placeholder('Unread'),
                        TextEntry::make('created_at')
                            ->since(),
                    ]),
            ]);
    }
}
