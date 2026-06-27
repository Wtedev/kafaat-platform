<?php

namespace App\Filament\Support;

use App\Enums\RegistrationStatus;

final class RegistrationStatusDisplay
{
    public static function beneficiaryLabel(RegistrationStatus|string|null $status): string
    {
        if ($status === null) {
            return '—';
        }

        if (is_string($status)) {
            $status = RegistrationStatus::tryFrom($status);
        }

        if ($status === null) {
            return '—';
        }

        return match ($status) {
            RegistrationStatus::Pending => 'قيد الانتظار',
            RegistrationStatus::Approved => 'لم يُنهِ',
            RegistrationStatus::Completed => 'مكتمل',
            RegistrationStatus::Rejected => 'مرفوض',
            RegistrationStatus::Cancelled => 'ملغي',
        };
    }

    public static function beneficiaryColor(RegistrationStatus|string|null $status): string
    {
        if ($status === null) {
            return 'gray';
        }

        if (is_string($status)) {
            $status = RegistrationStatus::tryFrom($status);
        }

        if ($status === null) {
            return 'gray';
        }

        return match ($status) {
            RegistrationStatus::Pending => 'warning',
            RegistrationStatus::Approved => 'info',
            RegistrationStatus::Completed => 'success',
            RegistrationStatus::Rejected => 'danger',
            RegistrationStatus::Cancelled => 'gray',
        };
    }
}
