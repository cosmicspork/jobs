<?php

use App\Mail\MonthlyUsageReport;
use App\Models\AiUsage;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use App\Relevance;
use Illuminate\Support\Facades\Mail;

it('emails users a usage report covering the previous month and skips silent users', function () {
    Mail::fake();

    $active = User::factory()->create();
    $silent = User::factory()->create();

    $lastMonth = now()->subMonthNoOverflow();

    AiUsage::factory()->create([
        'user_id' => $active->id,
        'cost' => 1.23,
        'created_at' => $lastMonth,
    ]);

    $listing = Listing::factory()->create();
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $active->id,
        'relevance' => Relevance::Relevant,
        'created_at' => $lastMonth,
        'updated_at' => $lastMonth,
    ]);

    $this->artisan('reports:monthly-usage')->assertSuccessful();

    Mail::assertSent(MonthlyUsageReport::class, fn ($mail) => $mail->hasTo($active->email));
    Mail::assertNotSent(MonthlyUsageReport::class, fn ($mail) => $mail->hasTo($silent->email));
});
