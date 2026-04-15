<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ListingStats;
use App\Filament\Widgets\ProfileCompletionChecklist;
use App\Mail\BoardRequested;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;

class Home extends Page
{
    protected string $view = 'filament.pages.home';

    protected static ?string $title = 'Home';

    protected static ?string $navigationLabel = 'Home';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = -1;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ProfileCompletionChecklist::class,
            ListingStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request a job board')
                    ->description("Know a job board we should be pulling from? Send it over — we'll review and add it as soon as we can.")
                    ->schema([
                        TextInput::make('name')
                            ->label('Board name')
                            ->placeholder('e.g. Indeed, LinkedIn, Dice')
                            ->required(),
                        TextInput::make('url')
                            ->label('URL')
                            ->url()
                            ->required(),
                        Textarea::make('notes')
                            ->label('Notes (optional)')
                            ->rows(3)
                            ->placeholder('Anything specific — search filters, role types, why this board?'),
                    ])
                    ->footerActions([
                        Action::make('submit')
                            ->label('Send Request')
                            ->submit('submit'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $adminEmail = config('scoring.admin_alert_email');

        if (! $adminEmail) {
            Notification::make()
                ->title('Could not send request')
                ->body('Admin email is not configured.')
                ->danger()
                ->send();

            return;
        }

        Mail::to($adminEmail)->send(new BoardRequested(
            user: auth()->user(),
            boardName: $data['name'],
            boardUrl: $data['url'],
            notes: $data['notes'] ?? null,
        ));

        $this->form->fill();

        Notification::make()
            ->title('Request sent')
            ->body("Thanks — we'll take a look.")
            ->success()
            ->send();
    }
}
