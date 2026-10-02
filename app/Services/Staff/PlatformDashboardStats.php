<?php

namespace App\Services\Staff;

use App\Enums\RegistrationStatus;
use App\Models\Certificate;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\User;
use App\Models\VolunteerRegistration;
use App\Support\Format\LocaleFormat;
use Illuminate\Support\Carbon;

final class PlatformDashboardStats
{
    /**
     * @return array{
     *     users: int,
     *     pending_paths: int,
     *     pending_programs: int,
     *     pending_volunteers: int,
     *     pending_total: int,
     *     pending_color: string,
     *     certificates_this_month: int,
     *     month_label: string
     * }
     */
    public function snapshot(?Carbon $now = null): array
    {
        $now ??= now();

        $pendingPaths = PathRegistration::query()->where('status', RegistrationStatus::Pending)->count();
        $pendingPrograms = ProgramRegistration::query()->where('status', RegistrationStatus::Pending)->count();
        $pendingVolunteers = VolunteerRegistration::query()->where('status', RegistrationStatus::Pending)->count();
        $totalPending = $pendingPaths + $pendingPrograms + $pendingVolunteers;

        return [
            'users' => User::query()->count(),
            'pending_paths' => $pendingPaths,
            'pending_programs' => $pendingPrograms,
            'pending_volunteers' => $pendingVolunteers,
            'pending_total' => $totalPending,
            'pending_color' => $totalPending > 0 ? 'warning' : 'success',
            'certificates_this_month' => Certificate::query()
                ->whereYear('issued_at', $now->year)
                ->whereMonth('issued_at', $now->month)
                ->count(),
            'month_label' => LocaleFormat::date($now, 'MMMM y'),
        ];
    }
}
