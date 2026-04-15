<?php

namespace App\Console\Commands;

use App\Mail\MonthlyUsageReport;
use App\Models\AiUsage;
use App\Models\Application;
use App\Models\ListingUser;
use App\Models\User;
use App\Relevance;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('reports:monthly-usage')]
#[Description('Email each user a usage report for the previous month')]
class SendMonthlyUsageReport extends Command
{
    public function handle(): int
    {
        $monthStart = now()->subMonthNoOverflow()->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $range = [$monthStart, $monthEnd];

        $sent = 0;

        foreach (User::cursor() as $user) {
            $aiCost = (float) AiUsage::query()
                ->where('user_id', $user->id)
                ->whereBetween('created_at', $range)
                ->sum('cost');

            $byRelevance = ListingUser::query()
                ->where('user_id', $user->id)
                ->whereBetween('created_at', $range)
                ->selectRaw('relevance, COUNT(*) as c')
                ->groupBy('relevance')
                ->pluck('c', 'relevance');

            $listingsReceived = (int) $byRelevance->sum();

            $applications = Application::query()
                ->where('user_id', $user->id)
                ->whereBetween('created_at', $range)
                ->count();

            if ($listingsReceived === 0 && $aiCost === 0.0 && $applications === 0) {
                continue;
            }

            Mail::to($user->email)->send(new MonthlyUsageReport(
                user: $user,
                monthStart: $monthStart,
                stats: [
                    'ai_cost' => $aiCost,
                    'listings_received' => $listingsReceived,
                    'relevant' => $byRelevance->get(Relevance::Relevant->value, 0),
                    'maybe' => $byRelevance->get(Relevance::Maybe->value, 0),
                    'irrelevant' => $byRelevance->get(Relevance::Irrelevant->value, 0),
                    'applications' => $applications,
                ],
            ));

            $sent++;
        }

        $this->info("Monthly usage report sent to {$sent} user(s).");

        return self::SUCCESS;
    }
}
