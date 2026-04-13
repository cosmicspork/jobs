<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        @foreach($this->getStats() as $stat)
            <x-filament::section>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $stat->getLabel() }}</div>
                <div class="text-3xl font-bold">{{ $stat->getValue() }}</div>
                @if($stat->getDescription())
                    <div class="text-sm text-gray-500">{{ $stat->getDescription() }}</div>
                @endif
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
