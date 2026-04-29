@php
    $user = auth()->user();
    $onProfile = request()->routeIs('filament.admin.pages.profile');
    $onHome = request()->routeIs('filament.admin.pages.home');
@endphp

@if ($user && ! $user->hasMinimumProfile() && ! $onProfile && ! $onHome)
    <div class="fi-banner bg-amber-100 dark:bg-amber-900/40 border-b border-amber-300 dark:border-amber-700 px-6 py-3">
        <div class="flex items-center justify-between gap-4">
            <div class="text-sm text-amber-900 dark:text-amber-100">
                <strong>Finish setting up your profile</strong> — scoring stays paused until you add a title, a summary, skills, and a remote preference.
            </div>
            <a href="{{ \App\Filament\Pages\Profile::getUrl() }}"
               class="text-sm font-semibold underline text-amber-900 dark:text-amber-100 hover:no-underline">
                Go to Profile →
            </a>
        </div>
    </div>
@endif
