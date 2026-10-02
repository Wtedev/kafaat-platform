<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\Staff\PlatformDashboardStats;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPermission('statistics.view');
    }

    protected function getStats(): array
    {
        $stats = app(PlatformDashboardStats::class)->snapshot();

        return [
            Stat::make('إجمالي المستخدمين', $stats['users'])
                ->description('مسجّلون في المنصة')
                ->color('primary')
                ->icon('heroicon-o-users'),

            Stat::make('طلبات معلّقة', $stats['pending_total'])
                ->description("مسارات: {$stats['pending_paths']} | برامج: {$stats['pending_programs']} | تطوع: {$stats['pending_volunteers']}")
                ->color($stats['pending_color'])
                ->icon('heroicon-o-clock'),

            Stat::make('شهادات هذا الشهر', $stats['certificates_this_month'])
                ->description($stats['month_label'])
                ->color('success')
                ->icon('heroicon-o-academic-cap'),
        ];
    }
}
