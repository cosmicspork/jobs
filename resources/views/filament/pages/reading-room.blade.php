@php
    use App\Models\Listing;

    $stageLabels = [
        'inbox' => 'Inbox',
        'starred' => 'Starred',
        'shortlisted' => 'Shortlist',
        'applied' => 'Applied',
    ];

    $fitScore = fn (Listing $row): ?int => $row->score_data['fit_score'] ?? null;

    // What would stop you applying, in the order it is worth reading.
    $rejectReasons = function (Listing $row): array {
        $data = $row->score_data ?? [];

        if (($data['filtered'] ?? false) && filled($data['filter_reason'] ?? null)) {
            return [str($data['filter_reason'])->replace('_', ' ')->toString()];
        }

        return array_slice($data['gaps'] ?? [], 0, 3);
    };

    $compensation = function (Listing $row): ?string {
        if (! $row->salary_min && ! $row->salary_max) {
            return null;
        }

        $format = fn (?int $v): ?string => $v ? '$'.number_format($v / 1000).'k' : null;

        return $row->salary_min && $row->salary_max
            ? $format($row->salary_min).'–'.$format($row->salary_max)
            : ($format($row->salary_min) ?? $format($row->salary_max));
    };
@endphp

<x-filament-panels::page>
    <div
        class="rr"
        x-data="readingRoom()"
        x-on:keydown.window="onKey($event)"
        :data-focus="focus"
    >
        <div class="sr-only" aria-live="polite" x-text="announcement"></div>

        {{-- Rail --}}
        <div class="rr-pane rr-rail rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="sticky top-0 z-10 grid grid-cols-4 gap-0.5 border-b border-gray-200 bg-white/95 p-1.5 backdrop-blur dark:border-white/10 dark:bg-gray-900/95">
                @foreach (\App\Filament\Pages\ReadingRoom::STAGES as $stageKey)
                    @php $count = $this->stageCounts[$stageKey] ?? 0; @endphp
                    <button
                        type="button"
                        wire:click="selectStage('{{ $stageKey }}')"
                        @class([
                            'flex flex-col items-center rounded-lg px-1 py-1.5 text-xs font-medium leading-tight transition',
                            'bg-primary-50 text-primary-700 dark:bg-primary-400/10 dark:text-primary-400' => $stage === $stageKey,
                            'text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/5' => $stage !== $stageKey,
                        ])
                        @if ($stage === $stageKey) aria-current="true" @endif
                    >
                        <span class="rr-score text-sm font-semibold">{{ $count }}</span>
                        <span>{{ $stageLabels[$stageKey] }}</span>
                    </button>
                @endforeach
            </div>

            <ul
                role="listbox"
                aria-label="{{ $stageLabels[$stage] ?? 'Queue' }} queue"
                class="divide-y divide-transparent"
            >
                @forelse ($this->rail as $row)
                    @php
                        $score = $fitScore($row);
                        $reasons = $rejectReasons($row);
                        $isSelected = $row->id === $selectedId;
                    @endphp
                    <li wire:key="row-{{ $row->id }}">
                        <button
                            type="button"
                            role="option"
                            class="rr-row"
                            style="--rr-score: {{ $score ?? 0 }}"
                            @if ($score === null) data-unscored @endif
                            aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                            tabindex="{{ $isSelected ? '0' : '-1' }}"
                            data-rr-row="{{ $row->id }}"
                            wire:click="select('{{ $row->id }}')"
                            x-on:click="focus = 'detail'"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-sm font-semibold leading-snug text-gray-950 dark:text-white">
                                    {{ $row->title }}
                                </span>
                                <span class="flex shrink-0 items-center gap-1 pt-0.5">
                                    @if ($row->applied_at)
                                        <x-filament::icon icon="heroicon-s-check-circle" class="h-4 w-4 text-success-500" />
                                    @elseif ($row->shortlisted_at)
                                        <x-filament::icon icon="heroicon-s-clipboard-document-check" class="h-4 w-4 text-success-500" />
                                    @elseif ($row->starred_at)
                                        <x-filament::icon icon="heroicon-s-star" class="h-4 w-4 text-warning-500" />
                                    @endif
                                    @if ($isSelected && $score !== null)
                                        <span class="rr-score text-xs font-medium text-gray-500 dark:text-gray-400">{{ $score }}</span>
                                    @endif
                                </span>
                            </div>

                            <div class="mt-0.5 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                <span class="truncate">{{ $row->companyName() }}</span>
                                @if ($comp = $compensation($row))
                                    <span aria-hidden="true">·</span>
                                    <span class="rr-score shrink-0">{{ $comp }}</span>
                                @endif
                            </div>

                            @if ($reasons !== [])
                                <div class="mt-1 truncate text-xs text-gray-400 dark:text-gray-500">
                                    {{ implode(' · ', $reasons) }}
                                </div>
                            @endif
                        </button>
                    </li>
                @empty
                    <li class="px-4 py-10 text-center">
                        <p class="text-sm font-medium text-gray-950 dark:text-white">
                            @if ($stage === 'inbox')
                                You're all caught up
                            @else
                                Nothing here yet
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            @switch ($stage)
                                @case('inbox') New matches land here as they are scored. @break
                                @case('starred') Star a listing to come back to it. @break
                                @case('shortlisted') Shortlist a listing to queue it up to apply for. @break
                                @default Listings you mark as applied will appear here.
                            @endswitch
                        </p>
                    </li>
                @endforelse
            </ul>

            @if ($this->hasMore())
                <div class="p-2">
                    <x-filament::button wire:click="loadMore" color="gray" size="sm" class="w-full">
                        Load more
                    </x-filament::button>
                </div>
            @endif
        </div>

        {{-- Detail --}}
        <div
            class="rr-pane rr-detail rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            wire:key="detail-{{ $selectedId ?? 'none' }}"
            wire:loading.class="opacity-60"
            x-effect="$el.scrollTop = 0"
        >
            @if ($listing = $this->selected)
                @php
                    $score = $fitScore($listing);
                    $data = $listing->score_data ?? [];
                @endphp

                <button
                    type="button"
                    class="mb-3 text-sm text-gray-500 lg:hidden dark:text-gray-400"
                    x-on:click="focus = 'rail'"
                >
                    &larr; Back to queue
                </button>

                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-xl font-semibold text-gray-950 dark:text-white">
                            {{ $listing->title }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $listing->companyName() }}
                            @if ($listing->remote) · Remote @endif
                            · {{ $listing->board }}
                        </p>
                        @if ($comp = $compensation($listing))
                            <p class="rr-score mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $comp }}</p>
                        @endif
                    </div>

                    @if (filled($listing->url))
                        <x-filament::button
                            tag="a"
                            href="{{ $listing->url }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            color="gray"
                            icon="heroicon-o-arrow-top-right-on-square"
                            data-rr-posting
                        >
                            Open posting
                        </x-filament::button>
                    @endif
                </div>

                {{-- Verdict --}}
                <div class="mt-4 rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                    <div class="flex flex-wrap items-center gap-3">
                        @if ($score !== null)
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 w-24 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div class="h-full rounded-full bg-gray-500 dark:bg-gray-400" style="width: {{ $score }}%"></div>
                                </div>
                                <span class="rr-score text-sm font-semibold text-gray-950 dark:text-white">{{ $score }}</span>
                            </div>
                        @endif
                        <x-filament::badge :color="$listing->relevance?->getColor() ?? 'gray'">
                            {{ $listing->relevance?->getLabel() ?? 'Unscored' }}
                        </x-filament::badge>
                        @if ($listing->target_name)
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $listing->target_name }}</span>
                        @endif
                        @if ($listing->outcome)
                            <x-filament::badge :color="$listing->outcome->getColor()">
                                {{ $listing->outcome->getLabel() }}
                            </x-filament::badge>
                        @endif
                    </div>

                    @if (filled($data['reasoning'] ?? null))
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $data['reasoning'] }}</p>
                    @endif

                    @foreach (['matched_skills' => 'Matched', 'gaps' => 'Gaps'] as $key => $label)
                        @if (filled($data[$key] ?? []))
                            <div class="mt-2 flex gap-2 text-xs">
                                <span class="w-16 shrink-0 text-gray-400 dark:text-gray-500">{{ $label }}</span>
                                <span class="text-gray-600 dark:text-gray-300">{{ implode(' · ', $data[$key]) }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Actions --}}
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-filament::button
                        wire:click="act('star')"
                        :color="$listing->starred_at ? 'warning' : 'gray'"
                        :icon="$listing->starred_at ? 'heroicon-s-star' : 'heroicon-o-star'"
                        size="sm"
                    >
                        {{ $listing->starred_at ? 'Starred' : 'Star' }}
                    </x-filament::button>

                    <x-filament::button
                        wire:click="act('shortlist')"
                        :color="$listing->shortlisted_at ? 'success' : 'gray'"
                        icon="heroicon-o-clipboard-document-check"
                        size="sm"
                    >
                        {{ $listing->shortlisted_at ? 'Shortlisted' : 'Shortlist' }}
                    </x-filament::button>

                    <x-filament::button
                        wire:click="act('applied')"
                        :color="$listing->applied_at ? 'success' : 'gray'"
                        icon="heroicon-o-check-circle"
                        size="sm"
                    >
                        {{ $listing->applied_at ? 'Applied' : 'Mark applied' }}
                    </x-filament::button>

                    <x-filament::button
                        wire:click="act('dismiss')"
                        color="danger"
                        icon="heroicon-o-archive-box-x-mark"
                        size="sm"
                    >
                        Not for me
                    </x-filament::button>
                </div>

                {{-- Description --}}
                @if (filled($this->descriptionHtml))
                    <div class="prose prose-sm mt-6 dark:prose-invert">
                        {!! $this->descriptionHtml !!}
                    </div>
                @else
                    <p class="mt-6 text-sm text-gray-500 dark:text-gray-400">No description was captured for this listing.</p>
                @endif
            @else
                <div class="flex h-full items-center justify-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Select a listing to read it.</p>
                </div>
            @endif
        </div>

        {{-- Keyboard help --}}
        {{-- Rendered only while open: the key guard looks for an open dialog,
             and a permanently-present one would swallow every shortcut. --}}
        <template x-if="helpOpen">
            <div
                x-on:keydown.escape.window="helpOpen = false"
                class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4"
                x-on:click.self="helpOpen = false"
                role="dialog"
                aria-modal="true"
                aria-label="Keyboard shortcuts"
            >
            <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Keyboard shortcuts</h3>
                <dl class="mt-3 space-y-1.5 text-sm">
                    @foreach ([
                        'j / k' => 'Move down / up',
                        's' => 'Star (press again to un-star)',
                        'l' => 'Shortlist',
                        'a' => 'Mark applied',
                        'e' => 'Not for me',
                        'r' => 'Toggle read',
                        'o' => 'Open the job posting',
                        '1 – 4' => 'Jump to a stage',
                        '?' => 'This list',
                    ] as $key => $description)
                        <div class="flex items-baseline gap-3">
                            <dt class="w-20 shrink-0 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $key }}</dt>
                            <dd class="text-gray-700 dark:text-gray-300">{{ $description }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    Shortcuts pause while you are typing in a field or a dialog is open.
                </p>
            </div>
            </div>
        </template>

        <p class="col-span-full text-center text-xs text-gray-400 dark:text-gray-500">
            <button type="button" x-on:click="helpOpen = true" class="hover:underline">
                Press <span class="font-mono">?</span> for keyboard shortcuts
            </button>
        </p>
    </div>

    @script
    <script>
        Alpine.data('readingRoom', () => ({
            focus: 'rail',
            helpOpen: false,
            announcement: '',
            dwellTimer: null,

            init() {
                this.$nextTick(() => this.focusSelected());
            },

            /** The stage keys, in the order the number keys address them. */
            stages: @js(\App\Filament\Pages\ReadingRoom::STAGES),

            onKey(event) {
                // Leave browser and Filament chords alone (⌘K opens global search).
                if (event.metaKey || event.ctrlKey || event.altKey) {
                    return;
                }

                const target = event.target;

                if (target instanceof Element) {
                    if (target.matches('input, textarea, select, [contenteditable]')) {
                        return;
                    }

                    if (target.closest('[role="dialog"], .fi-modal, .fi-dropdown-panel')) {
                        return;
                    }
                }

                // Any open modal owns the keyboard, including our own help panel.
                if (document.querySelector('[role="dialog"][aria-modal="true"]')) {
                    return;
                }

                const stageIndex = ['1', '2', '3', '4'].indexOf(event.key);

                if (stageIndex !== -1) {
                    return this.handled(event, this.$wire.selectStage(this.stages[stageIndex]));
                }

                switch (event.key) {
                    case 'j':
                    case 'ArrowDown':
                        return this.handled(event, this.$wire.move(1));
                    case 'k':
                    case 'ArrowUp':
                        return this.handled(event, this.$wire.move(-1));
                    case 's':
                        return this.handled(event, this.act('star', 'Starred'));
                    case 'l':
                        return this.handled(event, this.act('shortlist', 'Shortlisted'));
                    case 'a':
                        return this.handled(event, this.act('applied', 'Marked applied'));
                    case 'e':
                        return this.handled(event, this.act('dismiss', 'Dismissed'));
                    case 'r':
                        return this.handled(event, this.act('read', 'Toggled read'));
                    case 'o':
                        return this.handled(event, this.openPosting());
                    case 'Enter':
                        this.focus = 'detail';
                        return this.handled(event, Promise.resolve());
                    case '?':
                        this.helpOpen = true;
                        return this.handled(event, Promise.resolve());
                }

                // Anything else is the browser's — never preventDefault() blindly.
            },

            handled(event, promise) {
                event.preventDefault();

                Promise.resolve(promise).then(() => {
                    this.$nextTick(() => this.focusSelected());
                });
            },

            act(verb, label) {
                return this.$wire.act(verb).then(() => {
                    const remaining = this.$el.querySelectorAll('[data-rr-row]').length;
                    this.announcement = `${label}. ${remaining} left in this stage.`;
                });
            },

            openPosting() {
                const link = this.$el.querySelector('[data-rr-posting]');

                // Called from a keydown, so this counts as a user gesture and
                // survives popup blocking. No round-trip, so we keep our place.
                if (link) {
                    window.open(link.href, '_blank', 'noopener');
                }

                return Promise.resolve();
            },

            focusSelected() {
                const row = this.$el.querySelector('[data-rr-row][aria-selected="true"]');

                if (! row) {
                    return;
                }

                row.focus({ preventScroll: true });

                const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                row.scrollIntoView({ block: 'nearest', behavior: reduced ? 'auto' : 'smooth' });

                this.scheduleRead();
            },

            /**
             * Marking read is on a dwell timer rather than on selection, so
             * arrowing past a listing does not silently drain the inbox.
             */
            scheduleRead() {
                clearTimeout(this.dwellTimer);
                this.dwellTimer = setTimeout(() => this.$wire.markSelectedRead(), 800);
            },
        }));
    </script>
    @endscript
</x-filament-panels::page>
