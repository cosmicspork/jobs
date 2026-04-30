<?php

namespace App\Filament\Pages;

use App\Models\TargetProfile;
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
            'title' => $user->title,
            'experience_years' => $user->experience_years,
            'summary' => $user->summary,
            'skills' => $user->skills ?? [],
            'experience' => $user->experience ?? [],
            'education' => $user->education ?? [],
            'targets' => $user->targetProfiles->map(fn (TargetProfile $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'is_active' => $t->is_active,
                'positioning' => $t->positioning,
                'target_titles' => $t->target_titles ?? [],
                'remote' => $t->criterion('remote'),
                'salary_min' => $t->criterion('salary_min'),
                'locations' => $t->criterion('locations', []),
                'must_have_keywords' => $t->criterion('must_have_keywords', []),
                'avoid_keywords' => $t->criterion('avoid_keywords', []),
                'sort_order' => $t->sort_order,
            ])->all(),
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
                        ->description('Your career identity. Used by every agent as the candidate baseline.')
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
                                ->columnSpan(4),
                            TextInput::make('experience_years')
                                ->label('Years of experience')
                                ->placeholder('e.g. 9+')
                                ->columnSpan(2),
                            Textarea::make('summary')
                                ->label('Career summary')
                                ->helperText('2-3 sentences on who you are. Per-target framing lives in each target below.')
                                ->rows(4)
                                ->required()
                                ->columnSpanFull(),
                        ]),
                    Section::make('Skills')
                        ->description('Technical and leadership skills — one flat list. Each target picks the most relevant subset at scoring/tailoring time.')
                        ->schema([
                            TagsInput::make('skills')
                                ->placeholder('Add a skill — Laravel, Kubernetes, Mentorship, etc.')
                                ->required(),
                        ]),
                    Section::make('Experience & education')
                        ->description('Optional but recommended. Resume tailoring works better with structured experience.')
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
                                ->itemLabel(fn (array $state): string => ($state['role'] ?? '').' — '.($state['company'] ?? '')),
                            TagsInput::make('education')
                                ->placeholder('e.g. B.S. Computer Science, University X'),
                        ]),
                    Section::make('Targets')
                        ->description('What roles you\'re hunting for. Each target is scored independently against every listing — add one per role type you\'re open to.')
                        ->schema([
                            Repeater::make('targets')
                                ->label('Target profiles')
                                ->schema([
                                    Grid::make(6)->schema([
                                        TextInput::make('name')
                                            ->placeholder('e.g. Engineering Manager roles')
                                            ->required()
                                            ->columnSpan(4),
                                        Toggle::make('is_active')
                                            ->label('Active')
                                            ->default(true)
                                            ->columnSpan(1),
                                        TextInput::make('sort_order')
                                            ->label('Order')
                                            ->numeric()
                                            ->default(0)
                                            ->columnSpan(1),
                                    ]),
                                    Textarea::make('positioning')
                                        ->label('Positioning')
                                        ->helperText('2-3 sentences: what role you\'re aiming for and why. The agents use this to frame summaries, cover letters, and answers.')
                                        ->rows(3)
                                        ->required(),
                                    TagsInput::make('target_titles')
                                        ->label('Target titles')
                                        ->placeholder('Engineering Manager, Director of Engineering, Head of Engineering')
                                        ->helperText('Job titles that count as a match for this target.')
                                        ->required(),
                                    Grid::make(3)->schema([
                                        Toggle::make('remote')->label('Remote required')->default(true),
                                        TextInput::make('salary_min')
                                            ->label('Minimum salary')
                                            ->numeric()
                                            ->prefix('$'),
                                        TagsInput::make('locations')
                                            ->placeholder('Remote, Austin TX, …'),
                                    ]),
                                    Grid::make(2)->schema([
                                        TagsInput::make('must_have_keywords')
                                            ->label('Must-have keywords')
                                            ->helperText('Listings missing all of these are marked irrelevant.'),
                                        TagsInput::make('avoid_keywords')
                                            ->label('Avoid keywords')
                                            ->helperText('Listings featuring these prominently are marked irrelevant.'),
                                    ]),
                                ])
                                ->addActionLabel('Add target')
                                ->collapsible()
                                ->itemLabel(fn (array $state): string => $state['name'] ?? '')
                                ->minItems(1),
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
                                ->helperText(fn (): ?string => auth()->user()->hasMinimumProfile()
                                    ? null
                                    : 'Finish your profile (title, summary, skills, and at least one active target with positioning, target titles, and a remote preference) before enabling — scoring is paused until then, so the digest would be empty.')
                                ->disabled(fn (): bool => ! auth()->user()->hasMinimumProfile())
                                ->saved()
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
        /** @var User $user */
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
            'digest_enabled' => $data['digest_enabled'] ?? true,
            'digest_time' => $data['digest_time'] ?? '08:00',
            'timezone' => $data['timezone'] ?? 'America/Chicago',
        ]);

        $this->syncTargets($user, $data['targets'] ?? []);

        $user->syncSubscribedBoards($data['boards'] ?? []);

        Notification::make()
            ->title('Profile saved')
            ->success()
            ->send();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncTargets(User $user, array $rows): void
    {
        $keptIds = [];

        foreach ($rows as $row) {
            $attrs = [
                'name' => $row['name'],
                'positioning' => $row['positioning'] ?? null,
                'target_titles' => $row['target_titles'] ?? [],
                'criteria' => [
                    'remote' => $row['remote'] ?? false,
                    'salary_min' => isset($row['salary_min']) && $row['salary_min'] !== '' ? (int) $row['salary_min'] : null,
                    'locations' => $row['locations'] ?? [],
                    'must_have_keywords' => $row['must_have_keywords'] ?? [],
                    'avoid_keywords' => $row['avoid_keywords'] ?? [],
                ],
                'is_active' => (bool) ($row['is_active'] ?? true),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];

            if (! empty($row['id'])) {
                $target = $user->targetProfiles()->where('id', $row['id'])->first();
                if ($target) {
                    $target->update($attrs);
                    $keptIds[] = $target->id;

                    continue;
                }
            }

            $created = $user->targetProfiles()->create($attrs);
            $keptIds[] = $created->id;
        }

        $user->targetProfiles()->whereNotIn('id', $keptIds)->delete();
    }
}
