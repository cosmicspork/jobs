<?php

namespace App\Filament\Resources\Listings\Schemas;

use App\Models\Listing;
use App\Models\ListingUser;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

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
                        TextEntry::make('remote')
                            ->state(fn (Listing $record): string => $record->remote ? 'Yes' : 'No')
                            ->badge(),
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
                Section::make('Match by target')
                    ->description('How this listing scored against each of your active targets.')
                    ->schema([
                        View::make('filament.infolists.target-scores')
                            ->viewData(fn (Listing $record): array => [
                                'pivots' => self::getPivotsForRecord($record),
                            ]),
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
                            ->getStateUsing(fn (Listing $record) => self::getBestPivot($record)?->read_at),
                        TextEntry::make('pivot_starred_at')
                            ->label('Starred')
                            ->since()
                            ->placeholder('Not starred')
                            ->getStateUsing(fn (Listing $record) => self::getBestPivot($record)?->starred_at),
                        TextEntry::make('pivot_shortlisted_at')
                            ->label('Shortlisted')
                            ->since()
                            ->placeholder('Not shortlisted')
                            ->getStateUsing(fn (Listing $record) => self::getBestPivot($record)?->shortlisted_at),
                        TextEntry::make('created_at')
                            ->since(),
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
            ->sortBy(fn (ListingUser $p) => $p->targetProfile->sort_order);
    }
}
