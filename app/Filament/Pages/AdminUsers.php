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
        return auth()->user()?->is_admin ?? false;
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
                'role_type' => $record->preferences['role_type'] ?? 'both',
                'summary' => $record->summary,
                'skills' => $record->skills ?? [],
                'experience' => $record->experience ?? [],
                'education' => $record->education ?? [],
                'remote' => $record->preferences['remote'] ?? true,
                'salary_min' => $record->preferences['salary_min'] ?? null,
                'locations' => $record->preferences['locations'] ?? [],
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
                        TextInput::make('title')->columnSpan(3),
                        TextInput::make('experience_years')->columnSpan(1),
                        Select::make('role_type')
                            ->options([
                                'em' => 'Management',
                                'ic' => 'IC',
                                'both' => 'Both',
                            ])
                            ->columnSpan(2),
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
                            ->itemLabel(fn (array $state): ?string => ($state['role'] ?? '').' — '.($state['company'] ?? '')),
                        TagsInput::make('education'),
                    ]),
                Section::make('Preferences')
                    ->columns(3)
                    ->schema([
                        Toggle::make('remote')->label('Remote Only'),
                        TextInput::make('salary_min')->numeric()->prefix('$'),
                        TagsInput::make('locations'),
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
