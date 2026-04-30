<?php

namespace App\Filament\Widgets;

use App\Models\Certificate;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\VolunteerRegistration;
use App\Models\User;
use App\Enums\RegistrationStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $pendingPaths        = PathRegistration::where('status', RegistrationStatus::Pending)->count();
        $pendingPrograms     = ProgramRegistration::where('status', RegistrationStatus::Pending)->count();
        $pendingVolunteers   = VolunteerRegistration::where('status', RegistrationStatus::Pending)->count();
        $totalPending        = $pendingPaths + $pendingPrograms + $pendingVolunteers;

        $certificatesThisMonth = Certificate::whereYear('issued_at', now()->year)
            ->whereMonth('issued_at', now()->month)
            ->count();

        return [
            Stat::make('إجمالي المستخدمين', User::count())
                ->description('مسجّلون في المنصة')
                ->color('primary')
                ->icon('heroicon-o-users'),

            Stat::make('طلبات معلّقة', $totalPending)
                ->description("مسارات: {$pendingPaths} | برامج: {$pendingPrograms} | تطوع: {$pendingVolunteers}")
                ->color($totalPending > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clock'),

            Stat::make('شهادات هذا الشهر', $certificatesThisMonth)
                ->description(now()->translatedFormat('F Y'))
                ->color('success')
                ->icon('heroicon-o-academic-cap'),
        ];
    }
}
