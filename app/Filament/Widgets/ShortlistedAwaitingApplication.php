<?php

namespace App\Filament\Widgets;

use App\Models\Listing;
use App\Models\ListingUser;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

/**
 * Home triage hero: listings the user has shortlisted but not yet generated
 * an application for — the actionable middle of the funnel.
 */
class ShortlistedAwaitingApplication extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Shortlisted — awaiting application';

    public function table(Table $table): Table
    {
        $userId = auth()->id();

        // One row per listing, using the best-relevance pivot (mirrors ListingsTable).
        $bestPivotId = DB::table('listing_user as inner_lu')
            ->select('inner_lu.id')
            ->whereColumn('inner_lu.listing_id', 'listings.id')
            ->where('inner_lu.user_id', $userId)
            ->orderByRaw(ListingUser::orderByRelevanceSql('inner_lu.relevance'))
            ->orderByDesc('inner_lu.scored_at')
            ->limit(1);

        return $table
            ->query(
                Listing::query()
                    ->join('listing_user', function ($join) use ($userId, $bestPivotId) {
                        $join->on('listings.id', '=', 'listing_user.listing_id')
                            ->where('listing_user.user_id', $userId)
                            ->whereRaw('listing_user.id = ('.$bestPivotId->toRawSql().')');
                    })
                    ->leftJoin('target_profiles', 'listing_user.target_profile_id', '=', 'target_profiles.id')
                    ->whereNotNull('listing_user.shortlisted_at')
                    ->whereNull('listing_user.dismissed_at')
                    ->whereDoesntHave('applications', fn ($q) => $q->where('user_id', $userId))
                    ->select([
                        'listings.*',
                        'listing_user.relevance',
                        'listing_user.shortlisted_at',
                        'target_profiles.name as target_name',
                    ])
                    ->orderByDesc('listing_user.shortlisted_at')
            )
            ->columns([
                TextColumn::make('title')
                    ->weight('bold')
                    ->limit(50),
                TextColumn::make('company'),
                TextColumn::make('target_name')
                    ->label('Target')
                    ->placeholder('—'),
                TextColumn::make('relevance')
                    ->label('Match')
                    ->badge()
                    ->color(fn (Listing $record) => $record->relevance?->getColor() ?? 'gray')
                    ->placeholder('Unscored'),
                TextColumn::make('shortlisted_at')
                    ->label('Shortlisted')
                    ->since()
                    ->color('gray'),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Listing $record): string => route('filament.admin.resources.listings.view', $record)),
            ])
            ->recordUrl(fn (Listing $record): string => route('filament.admin.resources.listings.view', $record))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentCheck)
            ->emptyStateHeading('Nothing waiting')
            ->emptyStateDescription('Shortlist listings to queue them here for application.');
    }
}
