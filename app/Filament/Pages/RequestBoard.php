<?php

namespace App\Filament\Pages;

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

class RequestBoard extends Page
{
    protected string $view = 'filament.pages.request-board';

    protected static ?string $title = 'Request a Job Board';

    protected static ?string $navigationLabel = 'Request a Board';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static ?int $navigationSort = 50;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tell us about the board')
                    ->description("We'll review and add it as soon as we can. You'll see new listings show up automatically once it's live.")
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
                            ->rows(4)
                            ->placeholder('Anything specific — search filters, role types, why this board?'),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('submit')
                ->label('Send Request')
                ->submit('submit'),
        ];
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
