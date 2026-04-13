<?php

namespace App\Filament\Resources\Listings\Schemas;

use App\Models\Listing;
use App\Models\ListingUser;
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
                Section::make('Relevance')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('pivot_relevance')
                            ->label('Relevance')
                            ->badge()
                            ->placeholder('Unscored')
                            ->getStateUsing(fn (Listing $record) => static::getPivot($record)?->relevance),
                        TextEntry::make('pivot_role_type')
                            ->label('Role Type')
                            ->placeholder('Unknown')
                            ->getStateUsing(fn (Listing $record) => static::getPivot($record)?->score_data['role_type'] ?? null),
                        TextEntry::make('pivot_scored_at')
                            ->label('Scored')
                            ->since()
                            ->placeholder('Not scored')
                            ->getStateUsing(fn (Listing $record) => static::getPivot($record)?->scored_at),
                        TextEntry::make('pivot_quality_signals')
                            ->label('Quality Signals')
                            ->badge()
                            ->color('info')
                            ->placeholder('None')
                            ->getStateUsing(fn (Listing $record) => static::getPivot($record)?->score_data['posting_quality_signals'] ?? null),
                        TextEntry::make('pivot_matched_skills')
                            ->label('Matched Skills')
                            ->badge()
                            ->color('success')
                            ->placeholder('None')
                            ->getStateUsing(fn (Listing $record) => static::getPivot($record)?->score_data['matched_skills'] ?? null),
                        TextEntry::make('pivot_gaps')
                            ->label('Gaps')
                            ->badge()
                            ->color('danger')
                            ->placeholder('None')
                            ->getStateUsing(fn (Listing $record) => static::getPivot($record)?->score_data['gaps'] ?? null),
                        TextEntry::make('pivot_reasoning')
                            ->label('Reasoning')
                            ->columnSpanFull()
                            ->placeholder('No reasoning')
                            ->getStateUsing(fn (Listing $record) => static::getPivot($record)?->score_data['reasoning'] ?? null),
                    ]),
                Section::make('Timestamps')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('scraped_at')
                            ->since(),
                        TextEntry::make('pivot_read_at')
                            ->label('Read')
                            ->since()
                            ->placeholder('Unread')
                            ->getStateUsing(fn (Listing $record) => static::getPivot($record)?->read_at),
                        TextEntry::make('pivot_starred_at')
                            ->label('Starred')
                            ->since()
                            ->placeholder('Not starred')
                            ->getStateUsing(fn (Listing $record) => static::getPivot($record)?->starred_at),
                        TextEntry::make('pivot_shortlisted_at')
                            ->label('Shortlisted')
                            ->since()
                            ->placeholder('Not shortlisted')
                            ->getStateUsing(fn (Listing $record) => static::getPivot($record)?->shortlisted_at),
                        TextEntry::make('created_at')
                            ->since(),
                    ]),
            ]);
    }

    private static function getPivot(Listing $record): ?ListingUser
    {
        static $cache = [];

        $key = $record->id.'_'.auth()->id();

        return $cache[$key] ??= ListingUser::forUserListing(auth()->id(), $record->id);
    }
}
