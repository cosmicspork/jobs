<?php

namespace App\Filament\Pages;

use App\Jobs\ExportUserData;
use App\Models\User;
use App\Services\ProfileExporter;
use App\Services\ProfileImporter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                                ->columnSpan(3),
                            TextInput::make('email')
                                ->email()
                                ->required()
                                ->columnSpan(3),
                            Textarea::make('summary')
                                ->label('Career summary')
                                ->helperText('2-3 sentences on who you are: technical depth, scope of work, current context. Avoid "seeking X" / aspiration language — that lives in each target\'s positioning.')
                                ->rows(4)
                                ->required()
                                ->columnSpanFull(),
                        ]),
                    Section::make('Skills')
                        ->description('Technical and leadership skills — one flat list. Each target picks the most relevant subset at scoring/tailoring time.')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            TagsInput::make('skills')
                                ->placeholder('Add a skill — Laravel, Kubernetes, Mentorship, etc.')
                                ->helperText('Add everything you can credibly claim. The resume agent picks 10-12 most relevant per target; the scorer reads the full list.')
                                ->required(),
                        ]),
                    Section::make('Experience & education')
                        ->description('Optional but strongly recommended — resume tailoring is much sharper with structured experience.')
                        ->collapsible()
                        ->collapsed()
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
                            Repeater::make('education')
                                ->label('Education')
                                ->helperText('Add degrees, certificates, or relevant training. Highlights can include capstone projects, research, awards, or coursework — the agent picks what\'s relevant per target.')
                                ->schema([
                                    TextInput::make('qualification')->required()->placeholder('e.g. B.S.'),
                                    TextInput::make('institution')->required()->placeholder('e.g. State University'),
                                    TextInput::make('field_of_study')->placeholder('e.g. Computer Science'),
                                    TextInput::make('period')->required()->placeholder('2014 - 2018'),
                                    TagsInput::make('highlights')
                                        ->placeholder('Add a highlight')
                                        ->helperText('Capstones, research, awards, relevant coursework — anything the agent can pull from when this entry is worth surfacing.'),
                                ])
                                ->addActionLabel('Add education entry')
                                ->collapsible()
                                ->itemLabel(fn (array $state): string => trim(($state['qualification'] ?? '').' — '.($state['institution'] ?? ''))),
                        ]),
                    Section::make('Targets')
                        ->description('What roles you\'re hunting for. Each target is scored and tailored independently — add one per career direction you\'re open to (e.g. one for management, one for senior IC).')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            Repeater::make('targets')
                                ->label('Target profiles')
                                ->schema([
                                    Hidden::make('id'),
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
                                ->helperText(fn (): string => auth()->user()->hasMinimumProfile()
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

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportUserData')
                ->label('Export my data')
                ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Export everything')
                ->modalDescription('We\'ll build a ZIP with your profile, target profiles, applications, question sets, listing interactions, AI usage, and your generated resume + cover letter PDFs. We\'ll email you a download link when it\'s ready (expires in 24 hours).')
                ->modalSubmitActionLabel('Queue export')
                ->action(function (): void {
                    /** @var User $user */
                    $user = auth()->user();
                    ExportUserData::dispatch($user);

                    Notification::make()
                        ->title('Export queued')
                        ->body('We\'ll email '.$user->email.' when your ZIP is ready.')
                        ->success()
                        ->send();
                }),

            Action::make('exportProfile')
                ->label('Export profile')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function (ProfileExporter $exporter): StreamedResponse {
                    /** @var User $user */
                    $user = auth()->user();
                    $payload = $exporter->export($user);

                    return response()->streamDownload(
                        fn () => print json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                        $exporter->filename($user),
                        ['Content-Type' => 'application/json'],
                    );
                }),

            Action::make('importProfile')
                ->label('Import profile')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->modalHeading('Import profile from a backup')
                ->modalDescription('Replaces your profile fields (summary, skills, experience, education, notification settings). Target profiles are matched by name — existing ones are updated in place, new ones are added, and any not in the file are deactivated (not deleted) so your application history stays intact.')
                ->modalSubmitActionLabel('Import')
                ->schema([
                    FileUpload::make('file')
                        ->label('Profile JSON')
                        ->acceptedFileTypes(['application/json'])
                        ->maxSize(1024)
                        ->disk('local')
                        ->directory('profile-imports/'.auth()->id())
                        ->required(),
                    Checkbox::make('confirm')
                        ->label('I understand my profile will be overwritten and targets not in this file will be deactivated.')
                        ->accepted()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $path = $data['file'] ?? null;
                    if (! is_string($path) || ! Storage::disk('local')->exists($path)) {
                        throw ValidationException::withMessages(['file' => 'Upload not found.']);
                    }

                    try {
                        try {
                            $parsed = json_decode(Storage::disk('local')->get($path), true, flags: JSON_THROW_ON_ERROR);
                        } catch (\JsonException) {
                            throw ValidationException::withMessages(['file' => 'The uploaded file is not valid JSON.']);
                        }

                        /** @var User $user */
                        $user = auth()->user();
                        $result = app(ProfileImporter::class)->import($user, $parsed);
                    } finally {
                        Storage::disk('local')->delete($path);
                    }

                    Notification::make()
                        ->title('Profile imported')
                        ->body("Added {$result['added']}, updated {$result['updated']}, deactivated {$result['deactivated']} target profile(s).")
                        ->success()
                        ->send();

                    $this->mount();
                }),
        ];
    }
}
