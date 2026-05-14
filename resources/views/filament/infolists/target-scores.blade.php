@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\ListingUser> $pivots */
    $relevanceColors = [
        'relevant' => 'success',
        'maybe' => 'warning',
        'irrelevant' => 'danger',
    ];
@endphp

@if ($pivots->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">No active targets — add one in your profile to start scoring.</p>
@else
    <div class="space-y-4">
        @foreach ($pivots as $pivot)
            @php
                $relevance = $pivot->relevance?->value;
                $color = $relevanceColors[$relevance] ?? 'gray';
                $data = $pivot->score_data ?? [];
            @endphp
            <div class="rounded-md border border-gray-200 dark:border-gray-700 p-4 space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <div class="font-semibold text-gray-900 dark:text-gray-100">
                        {{ $pivot->targetProfile->name }}
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="fi-badge fi-color-{{ $color }} inline-flex items-center justify-center gap-x-1 rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset
                            @if($color === 'success') bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-400/10 dark:text-green-400 dark:ring-green-400/30
                            @elseif($color === 'warning') bg-yellow-50 text-yellow-800 ring-yellow-600/20 dark:bg-yellow-400/10 dark:text-yellow-400 dark:ring-yellow-400/30
                            @elseif($color === 'danger') bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-400 dark:ring-red-400/30
                            @else bg-gray-50 text-gray-600 ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/30 @endif">
                            {{ $pivot->relevance?->getLabel() ?? 'Unscored' }}
                        </span>
                        @if ($pivot->scored_at)
                            <span class="text-gray-500 dark:text-gray-400">{{ $pivot->scored_at->diffForHumans() }}</span>
                            @if ($pivot->targetProfile->updated_at->gt($pivot->scored_at))
                                <span class="fi-badge fi-color-warning inline-flex items-center justify-center gap-x-1 rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset bg-yellow-50 text-yellow-800 ring-yellow-600/20 dark:bg-yellow-400/10 dark:text-yellow-400 dark:ring-yellow-400/30"
                                    title="The target was edited after this score was computed. Click Re-score to refresh.">
                                    Target updated since
                                </span>
                            @endif
                        @endif
                    </div>
                </div>

                @if (! empty($data['reasoning']))
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $data['reasoning'] }}</p>
                @endif

                @if (! empty($data['matched_skills']))
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                        <span class="font-medium text-gray-700 dark:text-gray-300">Matched:</span>
                        {{ implode(', ', $data['matched_skills']) }}
                    </div>
                @endif

                @if (! empty($data['gaps']))
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                        <span class="font-medium text-gray-700 dark:text-gray-300">Gaps:</span>
                        {{ implode(', ', $data['gaps']) }}
                    </div>
                @endif

                @if (! empty($data['posting_quality_signals']))
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                        <span class="font-medium text-gray-700 dark:text-gray-300">Quality signals:</span>
                        {{ implode(', ', $data['posting_quality_signals']) }}
                    </div>
                @endif

                @if (! empty($data['filtered']))
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                        <span class="font-medium text-gray-700 dark:text-gray-300">Filtered:</span>
                        {{ $data['filter_reason'] ?? 'unknown' }}
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
