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
        <div class="space-y-6">
            @foreach ($results as $index => $result)
                <x-filament::section>
                    <x-slot name="heading">
                        {{ $result['question'] }}
                    </x-slot>

                    <div class="space-y-4">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Your Original Answer</h4>
                            <div class="rounded-lg bg-gray-50 dark:bg-white/5 p-3 text-sm">
                                {{ $result['answer'] }}
                            </div>
                        </div>

                        <div>
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Feedback</h4>
                            <div class="rounded-lg border border-info-300 bg-info-50 dark:border-info-700 dark:bg-info-950 p-3 text-sm">
                                {{ $result['feedback'] }}
                            </div>
                        </div>

                        @if ($result['grammar_corrections'] && $result['grammar_corrections'] !== 'No issues found.')
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Grammar & Style Notes</h4>
                                <div class="rounded-lg border border-warning-300 bg-warning-50 dark:border-warning-700 dark:bg-warning-950 p-3 text-sm">
                                    {{ $result['grammar_corrections'] }}
                                </div>
                            </div>
                        @endif

                        <div>
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Suggested Response</h4>
                            <textarea
                                wire:model.blur="results.{{ $index }}.suggested_answer"
                                rows="5"
                                class="fi-textarea block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white dark:focus:border-primary-500"
                            ></textarea>
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>

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
