@php
    $user = auth()->user();
    $hideOnRoutes = ['filament.admin.pages.profile', 'filament.admin.pages.home'];
@endphp

@if ($user && ! $user->hasMinimumProfile() && ! request()->routeIs(...$hideOnRoutes))
    <div class="px-6 pt-4">
        <x-filament::callout
            color="warning"
            icon="heroicon-m-exclamation-triangle"
            heading="Finish setting up your profile"
            description="Scoring stays paused until you add a summary, skills, and at least one active target."
        >
            <x-slot name="controls">
                <x-filament::link
                    :href="\App\Filament\Pages\Profile::getUrl()"
                    color="warning"
                    weight="semibold"
                    tag="a"
                >
                    Go to Profile
                </x-filament::link>
            </x-slot>
        </x-filament::callout>
    </div>
@endif
