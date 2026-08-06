<?php

namespace App\Filament\Pages;

use App\Models\Listing;
use App\Models\ListingUser;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Url;

/**
 * Triage surface: the queue on the left, the full listing on the right.
 *
 * The listings table answers "what is in my pipeline". This answers "what do I
 * do with the next one" — acting on a listing advances to its successor without
 * navigating, which is the loop the table could not serve.
 *
 * @property-read Collection<int, Listing> $rail
 * @property-read Listing|null $selected
 * @property-read string $descriptionHtml
 * @property-read array<string, int> $stageCounts
 */
class ReadingRoom extends Page
{
    protected string $view = 'filament.pages.reading-room';

    protected static ?string $title = 'Reading room';

    protected static ?string $navigationLabel = 'Reading room';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?int $navigationSort = 0;

    /** Stage keys shared with the listings table, in pipeline order. */
    public const STAGES = ['inbox', 'starred', 'shortlisted', 'applied'];

    #[Url]
    public string $stage = 'inbox';

    #[Url(as: 'listing')]
    public ?string $selectedId = null;

    public int $railLimit = 50;

    public const RAIL_PAGE = 50;

    public const RAIL_MAX = 250;

    public function mount(): void
    {
        if (! in_array($this->stage, self::STAGES, true)) {
            $this->stage = 'inbox';
        }

        $this->ensureSelection();
    }

    /**
     * Rows for the rail. Deliberately excludes `description` — it is the
     * largest column in the table and the rail never renders it.
     *
     * @return Collection<int, Listing>
     */
    #[Computed]
    public function rail(): Collection
    {
        $query = ListingUser::joinBestPivot(Listing::query(), auth()->id())
            ->whereNull('listing_user.dismissed_at')
            ->select([
                'listings.id',
                'listings.title',
                'listings.company',
                'listings.board',
                'listings.salary_min',
                'listings.salary_max',
                'listings.remote',
                'listing_user.relevance',
                'listing_user.score_data',
                'listing_user.scored_at',
                'listing_user.read_at',
                'listing_user.starred_at',
                'listing_user.shortlisted_at',
                'listing_user.applied_at',
                'listing_user.outcome',
                'target_profiles.name as target_name',
            ]);

        ListingUser::applyStage($query, $this->stage, 'listing_user.');

        return $query
            ->orderByDesc($this->orderColumn())
            ->limit($this->railLimit)
            ->get();
    }

    #[Computed]
    public function selected(): ?Listing
    {
        if ($this->selectedId === null) {
            return null;
        }

        return ListingUser::joinBestPivot(Listing::query(), auth()->id())
            ->where('listings.id', $this->selectedId)
            ->select([
                'listings.*',
                'listing_user.relevance',
                'listing_user.score_data',
                'listing_user.scored_at',
                'listing_user.read_at',
                'listing_user.starred_at',
                'listing_user.shortlisted_at',
                'listing_user.applied_at',
                'listing_user.outcome',
                'listing_user.dismissed_at',
                'target_profiles.name as target_name',
            ])
            ->first();
    }

    /**
     * Descriptions are third-party HTML scraped from job boards. Unlike the
     * infolist's ->html(), a Blade page has no sanitiser in front of it, so
     * raw HTML is stripped at the CommonMark level before it is rendered.
     */
    #[Computed]
    public function descriptionHtml(): string
    {
        $description = $this->selected?->description;

        if (blank($description)) {
            return '';
        }

        return Str::markdown($description, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /** @return array<string, int> */
    #[Computed]
    public function stageCounts(): array
    {
        $userId = auth()->id();

        return collect(self::STAGES)
            ->mapWithKeys(function (string $stage) use ($userId): array {
                $query = ListingUser::query()
                    ->where('user_id', $userId)
                    ->whereNull('dismissed_at');

                ListingUser::applyStage($query, $stage);

                return [$stage => (int) $query->distinct()->count('listing_id')];
            })
            ->all();
    }

    public function selectStage(string $stage): void
    {
        if (! in_array($stage, self::STAGES, true)) {
            return;
        }

        $this->stage = $stage;
        $this->railLimit = self::RAIL_PAGE;
        $this->selectedId = null;

        $this->refreshRail();
        $this->ensureSelection();
    }

    public function select(string $listingId): void
    {
        $this->selectedId = $listingId;
    }

    public function move(int $offset): void
    {
        $ids = $this->rail->pluck('id')->all();

        if ($ids === []) {
            return;
        }

        $current = array_search($this->selectedId, $ids, true);
        $next = $current === false ? 0 : $current + $offset;

        if ($next < 0 || $next >= count($ids)) {
            return;
        }

        $this->selectedId = $ids[$next];
    }

    /**
     * Acting advances to the successor. The successor is resolved before the
     * write, because the acted-on row usually drains out of the stage and its
     * neighbours shift.
     */
    public function act(string $verb): void
    {
        if ($this->selectedId === null) {
            return;
        }

        $ids = $this->rail->pluck('id')->all();
        $index = array_search($this->selectedId, $ids, true);
        $successor = $index === false ? null : ($ids[$index + 1] ?? $ids[$index - 1] ?? null);

        $pivot = ListingUser::forUserListing(auth()->id(), $this->selectedId);

        if (! $pivot instanceof ListingUser) {
            return;
        }

        match ($verb) {
            'star' => $pivot->starred_at ? $pivot->toggleStarred() : $pivot->star(),
            'shortlist' => $pivot->shortlisted_at ? $pivot->toggleShortlisted() : $pivot->shortlist(),
            'applied' => $pivot->toggleApplied(),
            'dismiss' => $pivot->toggleDismissed(),
            'read' => $pivot->toggleRead(),
            default => null,
        };

        $this->refreshRail();

        $remaining = $this->rail->pluck('id')->all();

        if (! in_array($this->selectedId, $remaining, true)) {
            $this->selectedId = in_array($successor, $remaining, true)
                ? $successor
                : ($remaining[0] ?? null);
        }
    }

    /**
     * Marking read is driven by a dwell timer in the browser rather than by
     * selection, so arrowing through the queue does not silently drain it.
     */
    #[Renderless]
    public function markSelectedRead(): void
    {
        if ($this->selectedId === null) {
            return;
        }

        $pivot = ListingUser::forUserListing(auth()->id(), $this->selectedId);

        if ($pivot instanceof ListingUser && $pivot->read_at === null) {
            $pivot->toggleRead();
        }
    }

    public function loadMore(): void
    {
        $this->railLimit = min($this->railLimit + self::RAIL_PAGE, self::RAIL_MAX);
        $this->refreshRail();
    }

    public function hasMore(): bool
    {
        return $this->rail->count() >= $this->railLimit
            && $this->railLimit < self::RAIL_MAX;
    }

    /** Stages are ordered by the timestamp that put a listing there. */
    private function orderColumn(): string
    {
        return match ($this->stage) {
            'starred' => 'listing_user.starred_at',
            'shortlisted' => 'listing_user.shortlisted_at',
            'applied' => 'listing_user.applied_at',
            default => 'listing_user.scored_at',
        };
    }

    private function refreshRail(): void
    {
        unset($this->rail, $this->selected, $this->stageCounts, $this->descriptionHtml);
    }

    private function ensureSelection(): void
    {
        $ids = $this->rail->pluck('id')->all();

        if ($this->selectedId === null || ! in_array($this->selectedId, $ids, true)) {
            $this->selectedId = $ids[0] ?? null;
        }
    }
}
