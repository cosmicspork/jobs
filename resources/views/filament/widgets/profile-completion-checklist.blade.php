<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Finish setting up your profile ({{ $this->getCompleteCount() }}/{{ count($this->getItems()) }})
        </x-slot>

        <x-slot name="description">
            Scoring stays paused until these fields are filled in.
        </x-slot>

        <x-slot name="afterHeader">
            <x-filament::icon-button
                icon="heroicon-m-x-mark"
                color="gray"
                label="Dismiss"
                wire:click="dismiss"
                wire:loading.attr="disabled"
            />
        </x-slot>

        <div class="grid grid-cols-2 gap-2.5">
            @foreach ($this->getItems() as $item)
                <x-filament::badge
                    :color="$item['done'] ? 'success' : 'danger'"
                    :icon="$item['done'] ? 'heroicon-m-check' : 'heroicon-m-x-mark'"
                    size="xl"
                >
                    {{ $item['label'] }}
                </x-filament::badge>
            @endforeach
        </div>

        <div class="mt-4">
            <x-filament::button :href="$this->getProfileUrl()" tag="a" size="sm">
                Open Profile
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
