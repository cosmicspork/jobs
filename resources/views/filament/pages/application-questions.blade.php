<x-filament-panels::page>
    @if (! $showResults)
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button wire:click="submitForReview" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submitForReview">Submit for Review</span>
                <span wire:loading wire:target="submitForReview">Reviewing...</span>
            </x-filament::button>
        </div>
    @else
        {{ $this->reviewForm }}

        <div class="mt-4 flex gap-3">
            <x-filament::button wire:click="saveFinalAnswers">
                Save Final Answers
            </x-filament::button>

            <x-filament::button wire:click="resubmit" color="gray" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="resubmit">Resubmit for More Feedback</span>
                <span wire:loading wire:target="resubmit">Reviewing...</span>
            </x-filament::button>
        </div>
    @endif
</x-filament-panels::page>
