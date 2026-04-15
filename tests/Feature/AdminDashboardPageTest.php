<?php

use App\Filament\Pages\AdminDashboard;
use App\Filament\Widgets\AdminOverviewStats;
use App\Models\AiUsage;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

it('is not accessible to non-admin users', function () {
    login(User::factory()->create(['is_admin' => false]));

    expect(AdminDashboard::canAccess())->toBeFalse();
});

it('uses the Overview navigation label', function () {
    expect(AdminDashboard::getNavigationLabel())->toBe('Overview');
});

it('renders for admin users', function () {
    login(User::factory()->create(['is_admin' => true]));

    $this->get(route('filament.admin.pages.admin-dashboard'))
        ->assertSuccessful();
});

it('overview stats widget reports global totals', function () {
    $admin = login(User::factory()->create(['is_admin' => true]));
    User::factory()->count(2)->create();

    Listing::factory()->count(5)->create();

    AiUsage::factory()->create(['user_id' => $admin->id, 'cost' => 1.23]);

    Livewire::test(AdminOverviewStats::class)
        ->assertSeeText('Users')
        ->assertSeeText('3')
        ->assertSeeText('Listings')
        ->assertSeeText('5')
        ->assertSeeText('$1.23');
});
