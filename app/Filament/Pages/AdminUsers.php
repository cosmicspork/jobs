<?php

namespace App\Filament\Pages;

use App\Mail\WelcomeUser;
use App\Models\TargetProfile;
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
                'title' => $record->title,
                'experience_years' => $record->experience_years,
                'summary' => $record->summary,
                'skills' => $record->skills ?? [],
                'experience' => $record->experience ?? [],
                'education' => $record->education ?? [],
                'targets' => $record->targetProfiles->map(fn (TargetProfile $t) => [
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
                'boards' => $record->subscribedBoardKeys(),
                'digest_enabled' => $record->digest_enabled,
                'digest_time' => $record->digest_time,
                'timezone' => $record->timezone,
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
                    ->columns(6)
                    ->schema([
                        TextInput::make('title')->columnSpan(4),
                        TextInput::make('experience_years')->columnSpan(2),
                        Textarea::make('summary')->rows(3)->columnSpanFull(),
                    ]),
                Section::make('Skills')
                    ->schema([
                        TagsInput::make('skills'),
                    ]),
                Section::make('Experience & education')
                    ->schema([
                        Repeater::make('experience')
                            ->schema([
                                TextInput::make('role')->required(),
                                TextInput::make('company')->required(),
                                TextInput::make('period')->required(),
                                TagsInput::make('highlights'),
                            ])
                            ->collapsible()
                            ->itemLabel(fn (array $state): string => ($state['role'] ?? '').' — '.($state['company'] ?? '')),
                        TagsInput::make('education'),
                    ]),
                Section::make('Targets')
                    ->schema([
                        Repeater::make('targets')
                            ->schema([
                                Grid::make(6)->schema([
                                    TextInput::make('name')->required()->columnSpan(4),
                                    Toggle::make('is_active')->label('Active')->default(true)->columnSpan(1),
                                    TextInput::make('sort_order')->label('Order')->numeric()->default(0)->columnSpan(1),
                                ]),
                                Textarea::make('positioning')->rows(3)->required(),
                                TagsInput::make('target_titles')->required(),
                                Grid::make(3)->schema([
                                    Toggle::make('remote')->label('Remote required')->default(true),
                                    TextInput::make('salary_min')->numeric()->prefix('$'),
                                    TagsInput::make('locations'),
                                ]),
                                Grid::make(2)->schema([
                                    TagsInput::make('must_have_keywords')->label('Must-have keywords'),
                                    TagsInput::make('avoid_keywords')->label('Avoid keywords'),
                                ]),
                            ])
                            ->collapsible()
                            ->itemLabel(fn (array $state): string => $state['name'] ?? ''),
                    ]),
                Section::make('Notifications')
                    ->columns(6)
                    ->schema([
                        CheckboxList::make('boards')
                            ->options(fn (): array => User::boardOptions())
                            ->columnSpanFull(),
                        Toggle::make('digest_enabled')->columnSpan(2),
                        TextInput::make('digest_time')->placeholder('08:00')->columnSpan(2),
                        Select::make('timezone')
                            ->options(fn (): array => User::timezoneOptions())
                            ->searchable()
                            ->columnSpan(2),
                    ]),
            ])
            ->action(function (array $data, User $record): void {
                $record->update([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'is_admin' => $data['is_admin'] ?? false,
                    'title' => $data['title'] ?? null,
                    'experience_years' => $data['experience_years'] ?? null,
                    'summary' => $data['summary'] ?? null,
                    'skills' => $data['skills'] ?? [],
                    'experience' => $data['experience'] ?? [],
                    'education' => $data['education'] ?? [],
                    'digest_enabled' => $data['digest_enabled'] ?? true,
                    'digest_time' => $data['digest_time'] ?? '08:00',
                    'timezone' => $data['timezone'] ?? 'America/Chicago',
                ]);

                $this->syncTargets($record, $data['targets'] ?? []);

                $record->syncSubscribedBoards($data['boards'] ?? []);

                Notification::make()->title('User updated')->success()->send();
            });
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
