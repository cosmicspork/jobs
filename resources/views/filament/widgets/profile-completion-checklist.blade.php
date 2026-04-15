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

        <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.625rem;">
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

        <div style="margin-top: 1rem;">
            <x-filament::button :href="$this->getProfileUrl()" tag="a" size="sm">
                Open Profile
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
