<?php

use App\Filament\Widgets\AiPerAgentStats;
use App\Filament\Widgets\AiUsageStats;
use App\Models\AiUsage;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = login(User::factory()->create(['is_admin' => true]));
});

it('renders the ai usage page', function () {
    $this->get(route('filament.admin.pages.ai-usage'))
        ->assertSuccessful();
});

it('displays usage stats in the widget', function () {
    AiUsage::factory()->create([
        'user_id' => $this->user->id,
        'model' => 'anthropic/claude-sonnet-4-6',
        'agent' => 'JobScorerAgent',
        'prompt_tokens' => 2000,
        'completion_tokens' => 800,
        'cost' => 0.018,
    ]);

    AiUsage::factory()->create([
        'user_id' => $this->user->id,
        'model' => 'anthropic/claude-haiku-4-5',
        'agent' => 'JobScorerAgent',
        'prompt_tokens' => 1000,
        'completion_tokens' => 400,
        'cost' => 0.0024,
    ]);

    Livewire::test(AiUsageStats::class)
        ->assertSee('Total Spend')
        ->assertSee('Total Requests')
        ->assertSee('Total Tokens')
        ->assertSee('$0.02');
});

it('displays per-agent average stats', function () {
    AiUsage::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'agent' => 'JobScorerAgent',
        'prompt_tokens' => 1000,
        'completion_tokens' => 500,
        'cost' => 0.01,
    ]);

    AiUsage::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'agent' => 'ResumeTailorAgent',
        'prompt_tokens' => 2000,
        'completion_tokens' => 1000,
        'cost' => 0.05,
    ]);

    Livewire::test(AiPerAgentStats::class)
        ->assertSee('Job Scoring')
        ->assertSee('Resume')
        ->assertSee('Cover Letter')
        ->assertSee('$0.0100/req')
        ->assertSee('3 requests')
        ->assertSee('$0.0500/req')
        ->assertSee('2 requests');
});
