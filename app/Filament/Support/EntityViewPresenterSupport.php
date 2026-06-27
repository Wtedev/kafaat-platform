<?php

namespace App\Filament\Support;

use App\Enums\OpportunityStatus;
use App\Enums\PathStatus;
use App\Enums\ProgramStatus;
use Illuminate\Support\Carbon;

final class EntityViewPresenterSupport
{
    /**
     * @return array{label: string, value: string, icon: string, badge?: string|null, field?: string|null}
     */
    public static function row(string $label, string $value, string $icon, ?string $badge = null, ?string $field = null): array
    {
        $row = [
            'label' => $label,
            'value' => $value,
            'icon' => $icon,
        ];

        if ($badge !== null) {
            $row['badge'] = $badge;
        }

        if ($field !== null) {
            $row['field'] = $field;
        }

        return $row;
    }

    /**
     * @return array{title: string, icon: string, prose: string, field?: string|null}
     */
    public static function proseSection(string $title, string $icon, string $prose, ?string $field = null): array
    {
        $section = [
            'title' => $title,
            'icon' => $icon,
            'prose' => trim($prose),
        ];

        if ($field !== null) {
            $section['field'] = $field;
        }

        return $section;
    }

    public static function formatDate(mixed $state): string
    {
        if ($state === null) {
            return 'غير محدد';
        }

        $date = $state instanceof Carbon ? $state : Carbon::parse($state);

        return $date->translatedFormat('j F Y');
    }

    public static function formatDateTime(mixed $state): string
    {
        if ($state === null) {
            return 'غير محدد';
        }

        $date = $state instanceof Carbon ? $state : Carbon::parse($state);

        return $date->timezone(config('app.timezone'))->translatedFormat('j F Y — H:i');
    }

    public static function formatDateRange(mixed $start, mixed $end): string
    {
        if ($start === null && $end === null) {
            return 'غير محدد';
        }

        if ($start !== null && $end !== null) {
            return self::formatDate($start).' — '.self::formatDate($end);
        }

        return self::formatDate($start ?? $end);
    }

    public static function formatCapacity(?int $capacity, int $approvedCount): string
    {
        if ($capacity === null) {
            return 'غير محدودة';
        }

        return sprintf('%d من %d مقعد', $approvedCount, $capacity);
    }

    public static function programStatusBadge(?ProgramStatus $status): ?string
    {
        return match ($status) {
            ProgramStatus::Published => 'success',
            ProgramStatus::Archived => 'warning',
            default => 'gray',
        };
    }

    public static function pathStatusBadge(?PathStatus $status): ?string
    {
        return match ($status) {
            PathStatus::Published => 'success',
            PathStatus::Archived => 'warning',
            default => 'gray',
        };
    }

    public static function opportunityStatusBadge(?OpportunityStatus $status): ?string
    {
        return match ($status) {
            OpportunityStatus::Published => 'success',
            OpportunityStatus::Archived => 'warning',
            default => 'gray',
        };
    }

    public static function registrationWindowBadge(string $label): ?string
    {
        return match ($label) {
            'مفتوح' => 'success',
            'لم يبدأ' => 'warning',
            'التسجيل عبر المسار' => 'info',
            'ممتلئ', 'منتهي', 'مغلق' => 'gray',
            default => 'gray',
        };
    }

    public static function newsPublicationBadge(string $label): ?string
    {
        return match ($label) {
            'منشور' => 'success',
            'مجدول' => 'warning',
            default => 'gray',
        };
    }

    public static function activeBadge(bool $isActive): string
    {
        return $isActive ? 'success' : 'gray';
    }
}
