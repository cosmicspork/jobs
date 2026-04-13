<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
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
                    $password = Str::random(16);

                    User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'password' => Hash::make($password),
                        'is_admin' => $data['is_admin'] ?? false,
                        'prompts' => config('profile-defaults.prompts'),
                    ]);

                    Notification::make()
                        ->title('User created')
                        ->body("Temporary password: {$password} — the user should reset via email.")
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
