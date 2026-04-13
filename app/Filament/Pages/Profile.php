<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

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

        $boardKeys = DB::table('board_user')
            ->where('user_id', $user->id)
            ->pluck('board_key')
            ->all();

        $this->form->fill([
            'name' => $user->name,
            'email' => $user->email,
            'title' => $user->title,
            'experience_years' => $user->experience_years,
            'summary_em' => $user->summaries['em'] ?? '',
            'summary_ic' => $user->summaries['ic'] ?? '',
            'leadership_skills' => $user->leadership_skills ?? [],
            'technical_depth' => $user->technical_depth ?? [],
            'experience' => $user->experience ?? [],
            'education' => $user->education ?? [],
            'remote' => $user->preferences['remote'] ?? true,
            'salary_min' => $user->preferences['salary_min'] ?? null,
            'locations' => $user->preferences['locations'] ?? [],
            'prompt_scorer' => $user->prompts['scorer'] ?? '',
            'prompt_resume' => $user->prompts['resume'] ?? '',
            'prompt_cover_letter' => $user->prompts['cover_letter'] ?? '',
            'prompt_application_questions' => $user->prompts['application_questions'] ?? '',
            'boards' => $boardKeys,
            'digest_enabled' => $user->digest_enabled,
            'digest_time' => $user->digest_time,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Basic Info')
                        ->columns(3)
                        ->schema([
                            TextInput::make('name')->required(),
                            TextInput::make('email')->email()->required(),
                            TextInput::make('title')->placeholder('e.g. Software Developer'),
                            TextInput::make('experience_years')->placeholder('e.g. 9+'),
                        ]),
                    Section::make('Summaries')
                        ->description('Pre-written career summaries used for resume tailoring.')
                        ->schema([
                            Textarea::make('summary_em')
                                ->label('Engineering Management Summary')
                                ->rows(3),
                            Textarea::make('summary_ic')
                                ->label('Individual Contributor Summary')
                                ->rows(3),
                        ]),
                    Section::make('Skills')
                        ->schema([
                            TagsInput::make('leadership_skills')
                                ->label('Leadership Skills')
                                ->placeholder('Add a skill'),
                            KeyValue::make('technical_depth')
                                ->label('Technical Depth')
                                ->keyLabel('Category')
                                ->valueLabel('Skills (comma-separated)')
                                ->addActionLabel('Add Category'),
                        ]),
                    Section::make('Experience')
                        ->schema([
                            Repeater::make('experience')
                                ->schema([
                                    TextInput::make('role')->required(),
                                    TextInput::make('company')->required(),
                                    TextInput::make('period')->required()->placeholder('June 2022 - Present'),
                                    TagsInput::make('highlights')
                                        ->placeholder('Add a highlight'),
                                ])
                                ->addActionLabel('Add Role')
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => ($state['role'] ?? '').' — '.($state['company'] ?? '')),
                        ]),
                    Section::make('Education')
                        ->schema([
                            TagsInput::make('education')
                                ->placeholder('Add a degree'),
                        ]),
                    Section::make('Preferences')
                        ->columns(3)
                        ->schema([
                            Toggle::make('remote')->label('Remote Only'),
                            TextInput::make('salary_min')
                                ->label('Minimum Salary')
                                ->numeric()
                                ->prefix('$'),
                            TagsInput::make('locations')
                                ->placeholder('Add location'),
                        ]),
                    Section::make('Board Subscriptions')
                        ->description('Select which job boards to receive listings from.')
                        ->schema([
                            CheckboxList::make('boards')
                                ->label('')
                                ->options(fn () => collect(config('boards'))->mapWithKeys(fn ($board, $key) => [$key => $board['name']])),
                        ]),
                    Section::make('Daily Digest')
                        ->columns(2)
                        ->schema([
                            Toggle::make('digest_enabled')->label('Enable Daily Digest'),
                            TextInput::make('digest_time')
                                ->label('Send Time')
                                ->placeholder('08:00'),
                        ]),
                    Section::make('AI Prompts')
                        ->description('Customize the prompts used by AI agents. Leave blank to use defaults.')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            Textarea::make('prompt_scorer')
                                ->label('Job Scoring Prompt')
                                ->rows(10)
                                ->placeholder('Leave blank for default'),
                            Textarea::make('prompt_resume')
                                ->label('Resume Tailor Prompt')
                                ->rows(10)
                                ->placeholder('Leave blank for default'),
                            Textarea::make('prompt_cover_letter')
                                ->label('Cover Letter Prompt')
                                ->rows(10)
                                ->placeholder('Leave blank for default'),
                            Textarea::make('prompt_application_questions')
                                ->label('Application Questions Prompt')
                                ->rows(10)
                                ->placeholder('Leave blank for default'),
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
            'summaries' => [
                'em' => $data['summary_em'] ?? '',
                'ic' => $data['summary_ic'] ?? '',
            ],
            'leadership_skills' => $data['leadership_skills'] ?? [],
            'technical_depth' => $data['technical_depth'] ?? [],
            'experience' => $data['experience'] ?? [],
            'education' => $data['education'] ?? [],
            'preferences' => [
                'remote' => $data['remote'] ?? true,
                'salary_min' => $data['salary_min'] ? (int) $data['salary_min'] : null,
                'locations' => $data['locations'] ?? [],
            ],
            'prompts' => array_filter([
                'scorer' => $data['prompt_scorer'] ?? null,
                'resume' => $data['prompt_resume'] ?? null,
                'cover_letter' => $data['prompt_cover_letter'] ?? null,
                'application_questions' => $data['prompt_application_questions'] ?? null,
            ]),
            'digest_enabled' => $data['digest_enabled'] ?? true,
            'digest_time' => $data['digest_time'] ?? '08:00',
        ]);

        // Sync board subscriptions
        DB::table('board_user')->where('user_id', $user->id)->delete();
        foreach ($data['boards'] ?? [] as $boardKey) {
            DB::table('board_user')->insert([
                'user_id' => $user->id,
                'board_key' => $boardKey,
                'created_at' => now(),
            ]);
        }

        Notification::make()
            ->title('Profile saved')
            ->success()
            ->send();
    }
}
