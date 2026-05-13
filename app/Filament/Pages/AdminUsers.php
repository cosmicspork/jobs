<?php

namespace App\Filament\Pages;

use App\Mail\WelcomeUser;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPasswordNotification;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AdminUsers extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.admin-users';

    protected static ?string $title = 'Users';

    protected static ?string $navigationLabel = 'Users';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 102;

    protected static string|\UnitEnum|null $navigationGroup = 'Admin';

    public static function canAccess(): bool
    {
        return auth()->user()->is_admin;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query())
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean(),
                IconColumn::make('digest_enabled')
                    ->label('Digest')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    $this->editUserAction(),
                    $this->sendPasswordResetAction(),
                ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createUser')
                ->label('Invite User')
                ->icon(Heroicon::OutlinedUserPlus)
                ->form([
                    TextInput::make('name')->required(),
                    TextInput::make('email')->email()->required()->unique('users', 'email'),
                    Toggle::make('is_admin')->label('Admin'),
                ])
                ->action(function (array $data): void {
                    $user = User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'password' => Hash::make(Str::random(40)),
                        'is_admin' => $data['is_admin'] ?? false,
                    ]);

                    $this->sendFilamentPasswordResetLink($user);
                    Mail::to($user->email)->send(new WelcomeUser($user));

                    Notification::make()
                        ->title('User invited')
                        ->body('Welcome and password-set emails were sent to '.$user->email.'.')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function editUserAction(): Action
    {
        return Action::make('edit')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading(fn (User $record) => "Edit {$record->name}")
            ->modalWidth('4xl')
            ->fillForm(fn (User $record): array => [
                'name' => $record->name,
                'email' => $record->email,
                'is_admin' => $record->is_admin,
                'experience_years' => $record->experience_years,
                'summary' => $record->summary,
                'skills' => $record->skills ?? [],
                'experience' => $record->experience ?? [],
                'education' => $record->education ?? [],
                'targets' => $record->targetProfilesForForm(),
                'boards' => $record->subscribedBoardKeys(),
                'digest_enabled' => $record->digest_enabled,
                'digest_time' => $record->digest_time,
                'timezone' => $record->timezone,
                'monthly_ai_cap_usd' => $record->monthly_ai_cap_usd,
            ])
            ->schema([
                Section::make('Account')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required(),
                        TextInput::make('email')->email()->required(),
                        Toggle::make('is_admin')->label('Admin'),
                    ]),
                Section::make('About')
                    ->description('Career identity. Keep direction-neutral — career aspirations belong in each target below.')
                    ->columns(6)
                    ->schema([
                        TextInput::make('experience_years')
                            ->helperText('Surfaces in the resume header.')
                            ->columnSpan(2),
                        Textarea::make('summary')
                            ->helperText('2-3 sentences on who they are. Avoid "seeking X" / aspiration language — that lives in each target\'s positioning.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Skills')
                    ->description('One flat list. Each target picks its own subset at scoring/tailoring time.')
                    ->schema([
                        TagsInput::make('skills')
                            ->helperText('The resume agent picks 10-12 most relevant per target; the scorer reads the full list.'),
                    ]),
                Section::make('Experience & education')
                    ->schema([
                        Repeater::make('experience')
                            ->helperText('Reverse-chronological. Each highlight should be a complete bullet ready for a resume.')
                            ->schema([
                                TextInput::make('role')->required(),
                                TextInput::make('company')->required(),
                                TextInput::make('period')->required(),
                                TagsInput::make('highlights')
                                    ->helperText('Action verb + what + scope/scale + result. The agent picks/reorders, never invents.'),
                            ])
                            ->collapsible()
                            ->itemLabel(fn (array $state): string => ($state['role'] ?? '').' — '.($state['company'] ?? '')),
                        TagsInput::make('education')
                            ->helperText('One entry per degree or certificate.'),
                    ]),
                Section::make('Targets')
                    ->description('Career directions. Each target is scored and tailored independently — one per role type.')
                    ->schema([
                        Repeater::make('targets')
                            ->schema([
                                Grid::make(6)->schema([
                                    TextInput::make('name')
                                        ->helperText('Short label — appears as a badge in the digest and listings.')
                                        ->required()
                                        ->columnSpan(4),
                                    Toggle::make('is_active')
                                        ->label('Active')
                                        ->helperText('Inactive targets are skipped during scraping/scoring.')
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
                                    ->helperText('2-3 sentences: what role they\'re aiming for and why. Canonical career-direction signal for every agent.')
                                    ->rows(3)
                                    ->required(),
                                TagsInput::make('target_titles')
                                    ->helperText('Job titles that count as a strong match.')
                                    ->required(),
                                Grid::make(3)->schema([
                                    Toggle::make('remote')
                                        ->label('Remote required')
                                        ->helperText('On-site-only listings auto-filtered.')
                                        ->default(true),
                                    TextInput::make('salary_min')
                                        ->helperText('Listings explicitly below this score lower; missing salary is not penalized.')
                                        ->numeric()
                                        ->prefix('$'),
                                    TagsInput::make('locations')
                                        ->helperText('Acceptable locations. "Remote" alone is fine.'),
                                ]),
                                Grid::make(2)->schema([
                                    TagsInput::make('must_have_keywords')
                                        ->label('Must-have keywords')
                                        ->helperText('Missing ALL → irrelevant. Usually leave empty.'),
                                    TagsInput::make('avoid_keywords')
                                        ->label('Avoid keywords')
                                        ->helperText('Featuring any → irrelevant. Cheapest noise filter.'),
                                ]),
                            ])
                            ->collapsible()
                            ->itemLabel(fn (array $state): string => $state['name'] ?? ''),
                    ]),
                Section::make('Notifications')
                    ->columns(6)
                    ->schema([
                        CheckboxList::make('boards')
                            ->helperText('Sources to pull listings from.')
                            ->options(fn (): array => User::boardOptions())
                            ->columnSpanFull(),
                        Toggle::make('digest_enabled')
                            ->helperText('Daily summary of new matches.')
                            ->columnSpan(2),
                        TextInput::make('digest_time')
                            ->placeholder('08:00')
                            ->helperText('24-hour clock, user\'s timezone.')
                            ->columnSpan(2),
                        Select::make('timezone')
                            ->options(fn (): array => User::timezoneOptions())
                            ->searchable()
                            ->columnSpan(2),
                    ]),
                Section::make('AI usage')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextInput::make('monthly_ai_cap_usd')
                            ->label('Monthly AI cap (USD)')
                            ->numeric()
                            ->prefix('$')
                            ->step('0.01')
                            ->placeholder('Default: $'.number_format((float) config('scoring.monthly_cap_usd'), 2))
                            ->helperText('Leave blank to use the system default.'),
                    ]),
            ])
            ->action(function (array $data, User $record): void {
                $record->update([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'is_admin' => $data['is_admin'] ?? false,
                    'experience_years' => $data['experience_years'] ?? null,
                    'summary' => $data['summary'] ?? null,
                    'skills' => $data['skills'] ?? [],
                    'experience' => $data['experience'] ?? [],
                    'education' => $data['education'] ?? [],
                    'digest_enabled' => $data['digest_enabled'] ?? false,
                    'digest_time' => $data['digest_time'] ?? '08:00',
                    'timezone' => $data['timezone'] ?? 'America/Chicago',
                    'monthly_ai_cap_usd' => filled($data['monthly_ai_cap_usd'] ?? null) ? $data['monthly_ai_cap_usd'] : null,
                ]);

                $record->syncTargetProfiles($data['targets'] ?? []);

                $record->syncSubscribedBoards($data['boards'] ?? []);

                Notification::make()->title('User updated')->success()->send();
            });
    }

    protected function sendPasswordResetAction(): Action
    {
        return Action::make('sendPasswordReset')
            ->label('Send Password Reset')
            ->icon(Heroicon::OutlinedKey)
            ->requiresConfirmation()
            ->action(function (User $record): void {
                $this->sendFilamentPasswordResetLink($record);

                Notification::make()
                    ->title('Password reset sent')
                    ->body('Link sent to '.$record->email)
                    ->success()
                    ->send();
            });
    }

    protected function sendFilamentPasswordResetLink(User $user): void
    {
        Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
            ['email' => $user->email],
            function (CanResetPassword $user, string $token): void {
                $notification = app(FilamentResetPasswordNotification::class, ['token' => $token]);
                $notification->url = Filament::getResetPasswordUrl($token, $user);

                $user->notify($notification);
            },
        );
    }
}
