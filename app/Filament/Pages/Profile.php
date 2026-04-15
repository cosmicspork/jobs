<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class Profile extends Page
{
    protected string $view = 'filament.pages.profile';

    protected static ?string $title = 'Profile';

    protected static ?string $navigationLabel = 'Profile';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static ?int $navigationSort = 98;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $user = auth()->user();

        $this->form->fill([
            'name' => $user->name,
            'email' => $user->email,
            'title' => $user->title,
            'experience_years' => $user->experience_years,
            'role_type' => $user->preferences['role_type'] ?? 'both',
            'summary' => $user->summary,
            'skills' => $user->skills ?? [],
            'experience' => $user->experience ?? [],
            'education' => $user->education ?? [],
            'remote' => $user->preferences['remote'] ?? true,
            'salary_min' => $user->preferences['salary_min'] ?? null,
            'locations' => $user->preferences['locations'] ?? [],
            'boards' => $user->subscribedBoardKeys(),
            'digest_enabled' => $user->digest_enabled,
            'digest_time' => $user->digest_time,
            'timezone' => $user->timezone,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('About you')
                        ->description('Used for scoring job listings and tailoring resumes.')
                        ->columns(6)
                        ->schema([
                            TextInput::make('name')
                                ->required()
                                ->columnSpan(3),
                            TextInput::make('email')
                                ->email()
                                ->required()
                                ->columnSpan(3),
                            TextInput::make('title')
                                ->placeholder('e.g. Senior Software Engineer')
                                ->required()
                                ->columnSpan(3),
                            TextInput::make('experience_years')
                                ->label('Years of experience')
                                ->placeholder('e.g. 9+')
                                ->columnSpan(1),
                            Select::make('role_type')
                                ->label('Looking for')
                                ->options([
                                    'em' => 'Management roles',
                                    'ic' => 'Individual contributor roles',
                                    'both' => 'Both — open to either',
                                ])
                                ->default('both')
                                ->required()
                                ->columnSpan(2),
                            Textarea::make('summary')
                                ->label('Professional summary')
                                ->helperText("2-3 sentences on who you are and what you're looking for. Used by the resume and cover letter agents.")
                                ->rows(4)
                                ->required()
                                ->columnSpanFull(),
                        ]),
                    Section::make('Skills')
                        ->description('Technical and leadership skills — one flat list. The scoring agent uses this to match you to listings.')
                        ->schema([
                            TagsInput::make('skills')
                                ->placeholder('Add a skill — Laravel, Kubernetes, Mentorship, etc.')
                                ->required(),
                        ]),
                    Section::make('Experience & education')
                        ->description('Optional. Resume tailoring works better with structured experience, but you can skip for now.')
                        ->collapsible()
                        ->collapsed(fn (): bool => empty(auth()->user()->experience) && empty(auth()->user()->education))
                        ->schema([
                            Repeater::make('experience')
                                ->label('Work history')
                                ->schema([
                                    TextInput::make('role')->required(),
                                    TextInput::make('company')->required(),
                                    TextInput::make('period')->required()->placeholder('June 2022 - Present'),
                                    TagsInput::make('highlights')->placeholder('Add a highlight'),
                                ])
                                ->addActionLabel('Add role')
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => ($state['role'] ?? '').' — '.($state['company'] ?? '')),
                            TagsInput::make('education')
                                ->placeholder('e.g. B.S. Computer Science, University X'),
                        ]),
                    Section::make('Job preferences')
                        ->columns(3)
                        ->schema([
                            Toggle::make('remote')->label('Remote only'),
                            TextInput::make('salary_min')
                                ->label('Minimum salary')
                                ->numeric()
                                ->prefix('$'),
                            TagsInput::make('locations')
                                ->placeholder('Add location'),
                        ]),
                    Section::make('Notifications')
                        ->description('Pick which boards to pull from and whether to get a daily digest email.')
                        ->collapsible()
                        ->collapsed()
                        ->columns(6)
                        ->schema([
                            CheckboxList::make('boards')
                                ->label('Job boards')
                                ->options(fn (): array => User::boardOptions())
                                ->columnSpanFull(),
                            Toggle::make('digest_enabled')
                                ->label('Daily digest email')
                                ->columnSpan(2),
                            TextInput::make('digest_time')
                                ->label('Send time (HH:MM)')
                                ->placeholder('08:00')
                                ->columnSpan(2),
                            Select::make('timezone')
                                ->label('Timezone')
                                ->options(fn (): array => User::timezoneOptions())
                                ->searchable()
                                ->required()
                                ->columnSpan(2),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save Profile')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'title' => $data['title'],
            'experience_years' => $data['experience_years'],
            'summary' => $data['summary'] ?? null,
            'skills' => $data['skills'] ?? [],
            'experience' => $data['experience'] ?? [],
            'education' => $data['education'] ?? [],
            'preferences' => [
                'remote' => $data['remote'] ?? true,
                'salary_min' => $data['salary_min'] ? (int) $data['salary_min'] : null,
                'locations' => $data['locations'] ?? [],
                'role_type' => $data['role_type'] ?? 'both',
            ],
            'digest_enabled' => $data['digest_enabled'] ?? true,
            'digest_time' => $data['digest_time'] ?? '08:00',
            'timezone' => $data['timezone'] ?? 'America/Chicago',
        ]);

        $user->syncSubscribedBoards($data['boards'] ?? []);

        Notification::make()
            ->title('Profile saved')
            ->success()
            ->send();
    }
}
