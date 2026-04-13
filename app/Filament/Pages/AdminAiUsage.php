<?php

namespace App\Filament\Pages;

use App\Models\AiUsage;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AdminAiUsage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.admin-ai-usage';

    protected static ?string $title = 'AI Usage (Global)';

    protected static ?string $navigationLabel = 'AI Usage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 101;

    protected static string|\UnitEnum|null $navigationGroup = 'Admin';

    public static function canAccess(): bool
    {
        return auth()->user()?->is_admin ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AiUsage::query()
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
                    ->numeric(),
                TextColumn::make('total_tokens')
                    ->label('Total Tokens')
                    ->formatStateUsing(fn ($state) => number_format($state)),
                TextColumn::make('total_cost')
                    ->label('Cost')
                    ->formatStateUsing(fn ($state) => '$'.number_format($state, 4)),
            ])
            ->paginated(false);
    }

    public function getTableRecordKey(Model|array $record): string
    {
        if (is_array($record)) {
            return $record['model'].'|'.$record['agent'];
        }

        return $record->getAttribute('model').'|'.$record->getAttribute('agent');
    }
}
