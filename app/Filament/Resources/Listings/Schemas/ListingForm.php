<?php

namespace App\Filament\Resources\Listings\Schemas;

use App\Relevance;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ListingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Job Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('company')
                            ->required(),
                        Toggle::make('remote')
                            ->inline(false),
                        TextInput::make('url')
                            ->label('Job URL')
                            ->url()
                            ->required()
                            ->maxLength(2048)
                            ->columnSpanFull(),
                        TextInput::make('source_url')
                            ->label('Source URL')
                            ->url()
                            ->maxLength(2048)
                            ->unique(ignoreRecord: true)
                            ->helperText('Where the listing was found. Leave blank to use the Job URL.')
                            ->columnSpanFull(),
                        TextInput::make('salary_min')
                            ->label('Salary Min')
                            ->numeric()
                            ->prefix('$'),
                        TextInput::make('salary_max')
                            ->label('Salary Max')
                            ->numeric()
                            ->prefix('$'),
                        Textarea::make('description')
                            ->required()
                            ->columnSpanFull()
                            ->rows(12)
                            ->helperText('Paste the full job posting here for better AI-generated documents.'),
                    ]),
                Section::make('Scoring Override')
                    ->schema([
                        Select::make('relevance')
                            ->options(Relevance::class)
                            ->placeholder('Unscored'),
                    ]),
            ]);
    }
}
