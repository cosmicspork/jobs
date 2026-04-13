<?php

namespace App\Filament\Widgets;

use App\Models\AiUsage;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;

class AiUsageBreakdownTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Usage by Model';

    public function getTableRecordKey(Model|array $record): string
    {
        if (is_array($record)) {
            return $record['model'].'|'.$record['agent'];
        }

        return $record->getAttribute('model').'|'.$record->getAttribute('agent');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AiUsage::query()
                    ->where('user_id', auth()->id())
                    ->selectRaw('model, agent, COUNT(*) as requests, SUM(prompt_tokens) as total_prompt_tokens, SUM(completion_tokens) as total_completion_tokens, SUM(prompt_tokens + completion_tokens) as total_tokens, SUM(cost) as total_cost')
                    ->groupBy('model', 'agent')
                    ->orderByDesc('total_cost')
            )
            ->columns([
                TextColumn::make('model')
                    ->label('Model')
                    ->formatStateUsing(fn (string $state) => AiUsage::shortModelName($state)),
                TextColumn::make('agent')
                    ->label('Agent'),
                TextColumn::make('requests')
                    ->label('Requests')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_prompt_tokens')
                    ->label('Input Tokens')
                    ->formatStateUsing(fn ($state) => number_format($state))
                    ->sortable(),
                TextColumn::make('total_completion_tokens')
                    ->label('Output Tokens')
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
            ->paginated(false)
            ->defaultKeySort(false);
    }
}
