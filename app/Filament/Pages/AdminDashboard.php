<?php

namespace App\Filament\Pages;

use App\Models\AiUsage;
use App\Models\Listing;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminDashboard extends Page
{
    protected string $view = 'filament.pages.admin-dashboard';

    protected static ?string $title = 'Admin Dashboard';

    protected static ?string $navigationLabel = 'Admin';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 100;

    protected static string|\UnitEnum|null $navigationGroup = 'Admin';

    public static function canAccess(): bool
    {
        return auth()->user()?->is_admin ?? false;
    }

    /**
     * @return array<Stat>
     */
    public function getStats(): array
    {
        $totalListings = Listing::count();
        $totalUsers = User::count();
        $totalAiCost = AiUsage::sum('cost');
        $todayCost = AiUsage::whereDate('created_at', today())->sum('cost');

        return [
            Stat::make('Total Listings', number_format($totalListings)),
            Stat::make('Total Users', $totalUsers),
            Stat::make('Total AI Spend', '$'.number_format($totalAiCost, 2))
                ->description('Today: $'.number_format($todayCost, 2)),
        ];
    }
}
