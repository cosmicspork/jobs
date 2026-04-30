<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Profile;
use Filament\Widgets\Widget;

class ProfileCompletionChecklist extends Widget
{
    protected string $view = 'filament.widgets.profile-completion-checklist';

    protected int|string|array $columnSpan = 'full';

    private const SESSION_KEY = 'profile_checklist_dismissed';

    /** @var array<int, array{label: string, done: bool}>|null */
    private ?array $items = null;

    public static function canView(): bool
    {
        if (auth()->user()?->hasMinimumProfile()) {
            return false;
        }

        return ! session(self::SESSION_KEY, false);
    }

    public function dismiss(): void
    {
        session([self::SESSION_KEY => true]);

        $this->redirect(request()->header('Referer') ?: '/', navigate: true);
    }

    /**
     * @return array<int, array{label: string, done: bool}>
     */
    public function getItems(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }

        $user = auth()->user();

        return $this->items = [
            ['label' => 'Job title', 'done' => ! empty($user->title)],
            ['label' => 'Summary', 'done' => ! empty($user->summary)],
            ['label' => 'Skills', 'done' => ! empty($user->skills)],
            ['label' => 'At least one active target', 'done' => $user->hasReadyTargetProfile()],
        ];
    }

    public function getCompleteCount(): int
    {
        return count(array_filter($this->getItems(), fn (array $item): bool => $item['done']));
    }

    public function getProfileUrl(): string
    {
        return Profile::getUrl();
    }
}
