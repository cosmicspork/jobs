<?php

namespace App\Filament\Widgets;

use App\Models\AiUsage;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;

class AiPerUserBreakdown extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Usage by User';

    public static function canView(): bool
    {
        return auth()->user()?->is_admin ?? false;
    }

    public function getTableRecordKey(Model|array $record): string
    {
        if (is_array($record)) {
            return (string) $record['user_id'];
        }

        return (string) $record->getAttribute('user_id');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AiUsage::query()
                    ->join('users', 'users.id', '=', 'ai_usages.user_id')
                    ->selectRaw('users.id as user_id, users.name as user_name, users.email as user_email')
                    ->selectRaw('COUNT(*) as requests')
                    ->selectRaw('SUM(prompt_tokens + completion_tokens) as total_tokens')
                    ->selectRaw('SUM(cost) as total_cost')
                    ->groupBy('users.id', 'users.name', 'users.email')
                    ->orderByDesc('total_cost')
            )
            ->columns([
                TextColumn::make('user_name')
                    ->label('User')
                    ->description(fn ($record): string => $record->user_email),
                TextColumn::make('requests')
                    ->label('Requests')
                    ->formatStateUsing(fn ($state) => number_format($state))
                    ->sortable(),
                TextColumn::make('total_tokens')
                    ->label('Total Tokens')
                    ->formatStateUsing(fn ($state) => number_format($state))
                    ->sortable(),
                TextColumn::make('total_cost')
                    ->label('Cost')
                    ->formatStateUsing(fn ($state) => '$'.number_format($state, 4))
                    ->sortable(),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->defaultKeySort(false);
    }
}
