<?php

use App\Filament\Pages\AdminAiUsage;
use App\Filament\Widgets\AiPerUserBreakdown;
use App\Filament\Widgets\AiUsageSummaryStats;
use App\Models\AiUsage;
use App\Models\User;
use Livewire\Livewire;

it('is not accessible to non-admin users', function () {
    login(User::factory()->create(['is_admin' => false]));

    expect(AdminAiUsage::canAccess())->toBeFalse();
});

it('renders for admin users', function () {
    login(User::factory()->create(['is_admin' => true]));

    $this->get(route('filament.admin.pages.admin-ai-usage'))
        ->assertSuccessful();
});

it('summary widget aggregates global spend and request counts', function () {
    $admin = login(User::factory()->create(['is_admin' => true]));
    $other = User::factory()->create();

    AiUsage::factory()->create([
        'user_id' => $admin->id,
        'prompt_tokens' => 1000,
        'completion_tokens' => 500,
        'cost' => 0.015,
    ]);
    AiUsage::factory()->create([
        'user_id' => $other->id,
        'prompt_tokens' => 2000,
        'completion_tokens' => 800,
        'cost' => 0.025,
    ]);

    Livewire::test(AiUsageSummaryStats::class)
        ->assertSeeText('$0.04')
        ->assertSeeText('Total Requests')
        ->assertSeeText('2');
});

it('per-user breakdown widget lists users sorted by cost', function () {
    $admin = login(User::factory()->create(['is_admin' => true, 'name' => 'Admin User']));
    $other = User::factory()->create(['name' => 'Other User']);

    AiUsage::factory()->create(['user_id' => $admin->id, 'cost' => 0.015]);
    AiUsage::factory()->create(['user_id' => $other->id, 'cost' => 0.050]);

    Livewire::test(AiPerUserBreakdown::class)
        ->assertSeeTextInOrder(['Other User', 'Admin User'])
        ->assertSeeText('$0.0500')
        ->assertSeeText('$0.0150');
});
