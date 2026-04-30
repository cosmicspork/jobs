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
use Filament\Schemas\Components\Grid;
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
            'experience_years' => $user->experience_years,
            'summary' => $user->summary,
            'skills' => $user->skills ?? [],
            'experience' => $user->experience ?? [],
            'education' => $user->education ?? [],
            'targets' => $user->targetProfilesForForm(),
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
                        ->description('Your career identity. Used by every agent as the candidate baseline. Keep this direction-neutral — career aspirations belong in each target below.')
                        ->columns(6)
                        ->schema([
                            TextInput::make('name')
                                ->required()
                                ->columnSpan(2),
                            TextInput::make('email')
                                ->email()
                                ->required()
                                ->columnSpan(2),
                            TextInput::make('experience_years')
                                ->label('Years of experience')
                                ->placeholder('e.g. 9+')
                                ->helperText('Surfaces in the resume header.')
                                ->columnSpan(2),
                            Textarea::make('summary')
                                ->label('Career summary')
                                ->helperText('2-3 sentences on who you are: technical depth, scope of work, current context. Avoid "seeking X" / aspiration language — that lives in each target\'s positioning.')
                                ->rows(4)
                                ->required()
                                ->columnSpanFull(),
                        ]),
                    Section::make('Skills')
                        ->description('Technical and leadership skills — one flat list. Each target picks the most relevant subset at scoring/tailoring time.')
                        ->schema([
                            TagsInput::make('skills')
                                ->placeholder('Add a skill — Laravel, Kubernetes, Mentorship, etc.')
                                ->helperText('Add everything you can credibly claim. The resume agent picks 10-12 most relevant per target; the scorer reads the full list.')
                                ->required(),
                        ]),
                    Section::make('Experience & education')
                        ->description('Optional but strongly recommended — resume tailoring is much sharper with structured experience.')
                        ->collapsible()
                        ->collapsed(fn (): bool => empty(auth()->user()->experience) && empty(auth()->user()->education))
                        ->schema([
                            Repeater::make('experience')
                                ->label('Work history')
                                ->helperText('Add roles in reverse-chronological order. Each highlight should be a complete bullet ready for a resume — the agent will pick and reorder them per target.')
                                ->schema([
                                    TextInput::make('role')->required()->placeholder('e.g. Senior Software Engineer'),
                                    TextInput::make('company')->required(),
                                    TextInput::make('period')->required()->placeholder('June 2022 - Present'),
                                    TagsInput::make('highlights')
                                        ->placeholder('Add a highlight')
                                        ->helperText('One sentence each. Use the format: action verb + what you did + scope/scale + result. The agent never invents — it only picks from these.'),
                                ])
                                ->addActionLabel('Add role')
                                ->collapsible()
                                ->itemLabel(fn (array $state): string => ($state['role'] ?? '').' — '.($state['company'] ?? '')),
                            TagsInput::make('education')
                                ->placeholder('e.g. B.S. Computer Science, University X')
                                ->helperText('One entry per degree or certificate. Surfaces in the resume\'s Education section.'),
                        ]),
                    Section::make('Targets')
                        ->description('What roles you\'re hunting for. Each target is scored and tailored independently — add one per career direction you\'re open to (e.g. one for management, one for senior IC).')
                        ->schema([
                            Repeater::make('targets')
                                ->label('Target profiles')
                                ->schema([
                                    Grid::make(6)->schema([
                                        TextInput::make('name')
                                            ->placeholder('e.g. Engineering Manager roles')
                                            ->helperText('A short label for this target — appears as a badge in the digest and listing views.')
                                            ->required()
                                            ->columnSpan(4),
                                        Toggle::make('is_active')
                                            ->label('Active')
                                            ->helperText('Inactive targets are skipped during scraping and scoring.')
                                            ->default(true)
                                            ->columnSpan(1),
                                        TextInput::make('sort_order')
                                            ->label('Order')
                                            ->helperText('Lower first.')
                                            ->numeric()
                                            ->default(0)
                                            ->columnSpan(1),
                                    ]),
                                    Textarea::make('positioning')
                                        ->label('Positioning')
                                        ->helperText('2-3 sentences: what role you\'re aiming for and why. This is the canonical career-direction signal for every agent (scoring, resume, cover letter).')
                                        ->rows(3)
                                        ->required(),
                                    TagsInput::make('target_titles')
                                        ->label('Target titles')
                                        ->placeholder('Engineering Manager, Director of Engineering, Head of Engineering')
                                        ->helperText('Job titles that count as a strong match. Adjacent titles (e.g. "VP Engineering" when you target "Director") still score — list the most representative ones.')
                                        ->required(),
                                    Grid::make(3)->schema([
                                        Toggle::make('remote')
                                            ->label('Remote required')
                                            ->helperText('When on, on-site-only listings are auto-filtered before AI scoring.')
                                            ->default(true),
                                        TextInput::make('salary_min')
                                            ->label('Minimum salary')
                                            ->numeric()
                                            ->prefix('$')
                                            ->helperText('Listings explicitly below this score lower; missing salary is not penalized.'),
                                        TagsInput::make('locations')
                                            ->placeholder('Remote, Austin TX, …')
                                            ->helperText('Acceptable locations. "Remote" alone is fine if you only want remote.'),
                                    ]),
                                    Grid::make(2)->schema([
                                        TagsInput::make('must_have_keywords')
                                            ->label('Must-have keywords')
                                            ->helperText('Listings missing ALL of these are marked irrelevant before AI scoring. Use sparingly — leaving empty is usually safer.'),
                                        TagsInput::make('avoid_keywords')
                                            ->label('Avoid keywords')
                                            ->helperText('Listings prominently featuring any of these are marked irrelevant before AI scoring. Cheapest way to cut noise.'),
                                    ]),
                                ])
                                ->addActionLabel('Add target')
                                ->collapsible()
                                ->itemLabel(fn (array $state): string => $state['name'] ?? '')
                                ->minItems(1),
                        ]),
                    Section::make('Notifications')
                        ->description('Which boards to pull from and when to receive your daily digest email.')
                        ->collapsible()
                        ->collapsed()
                        ->columns(6)
                        ->schema([
                            CheckboxList::make('boards')
                                ->label('Job boards')
                                ->options(fn (): array => User::boardOptions())
                                ->helperText('Sources to pull listings from. Only listings scraped from selected boards become candidates for your targets.')
                                ->columnSpanFull(),
                            Toggle::make('digest_enabled')
                                ->label('Daily digest email')
                                ->helperText(fn (): ?string => auth()->user()->hasMinimumProfile()
                                    ? 'Sends a daily summary of new relevant/maybe matches at the time below.'
                                    : 'Finish your profile (summary, skills, and at least one active target with positioning, target titles, and a remote preference) before enabling — scoring is paused until then, so the digest would be empty.')
                                ->disabled(fn (): bool => ! auth()->user()->hasMinimumProfile())
                                ->saved()
                                ->columnSpan(2),
                            TextInput::make('digest_time')
                                ->label('Send time (HH:MM)')
                                ->placeholder('08:00')
                                ->helperText('24-hour clock, in your timezone below.')
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
        /** @var User $user */
        $user = auth()->user();

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'experience_years' => $data['experience_years'],
            'summary' => $data['summary'] ?? null,
            'skills' => $data['skills'] ?? [],
            'experience' => $data['experience'] ?? [],
            'education' => $data['education'] ?? [],
            'digest_enabled' => $data['digest_enabled'] ?? false,
            'digest_time' => $data['digest_time'] ?? '08:00',
            'timezone' => $data['timezone'] ?? 'America/Chicago',
        ]);

        $user->syncTargetProfiles($data['targets'] ?? []);

        $user->syncSubscribedBoards($data['boards'] ?? []);

        Notification::make()
            ->title('Profile saved')
            ->success()
            ->send();
    }
}
