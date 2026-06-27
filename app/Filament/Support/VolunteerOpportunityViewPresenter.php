<?php

namespace App\Filament\Support;

use App\Enums\OpportunityStatus;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use Illuminate\Support\Carbon;

final class VolunteerOpportunityViewPresenter
{
    /**
     * @return array{stats: array<int, array{label: string, value: string, icon: string}>, sections: array<int, array<string, mixed>>}
     */
    public static function present(VolunteerOpportunity $opportunity): array
    {
        return [
            'stats' => self::stats($opportunity),
            'sections' => array_values(array_filter([
                self::overviewSection($opportunity),
                self::scheduleSection($opportunity),
                self::enrollmentSection($opportunity),
                self::teamSection($opportunity),
                self::descriptionSection($opportunity),
            ])),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, icon: string}>
     */
    private static function stats(VolunteerOpportunity $opportunity): array
    {
        $hours = $opportunity->hours_expected;

        return [
            [
                'label' => 'المسجّلون',
                'value' => (string) ($opportunity->registrations_count ?? $opportunity->registrations()->count()),
                'icon' => 'heroicon-o-users',
            ],
            [
                'label' => 'المقبولون',
                'value' => (string) $opportunity->approvedRegistrationsCount(),
                'icon' => 'heroicon-o-check-circle',
            ],
            [
                'label' => 'الساعات المتوقعة',
                'value' => $hours !== null ? "{$hours} ساعة" : '—',
                'icon' => 'heroicon-o-clock',
            ],
        ];
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>}
     */
    private static function overviewSection(VolunteerOpportunity $opportunity): array
    {
        $registrationLabel = self::registrationLabel($opportunity);

        return [
            'title' => 'نظرة عامة',
            'icon' => 'heroicon-o-eye',
            'rows' => [
                EntityViewPresenterSupport::row(
                    'حالة الفرصة',
                    $opportunity->status?->label() ?? 'غير محدد',
                    'heroicon-o-signal',
                    EntityViewPresenterSupport::opportunityStatusBadge($opportunity->status),
                ),
                EntityViewPresenterSupport::row(
                    'حالة التسجيل',
                    $registrationLabel,
                    'heroicon-o-clipboard-document-check',
                    EntityViewPresenterSupport::registrationWindowBadge($registrationLabel),
                ),
            ],
        ];
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>}
     */
    private static function scheduleSection(VolunteerOpportunity $opportunity): array
    {
        $rows = [
            EntityViewPresenterSupport::row(
                'فترة الفرصة',
                EntityViewPresenterSupport::formatDateRange($opportunity->start_date, $opportunity->end_date),
                'heroicon-o-calendar-days',
            ),
            EntityViewPresenterSupport::row(
                'مدة الفرصة',
                self::durationDescription($opportunity),
                'heroicon-o-arrow-path',
            ),
        ];

        if ($opportunity->status === OpportunityStatus::Published && $opportunity->published_at !== null) {
            $rows[] = EntityViewPresenterSupport::row(
                'تاريخ النشر',
                EntityViewPresenterSupport::formatDate($opportunity->published_at),
                'heroicon-o-globe-alt',
            );
        }

        return [
            'title' => 'الجدول الزمني',
            'icon' => 'heroicon-o-calendar',
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>}
     */
    private static function enrollmentSection(VolunteerOpportunity $opportunity): array
    {
        return [
            'title' => 'التسجيل والسعة',
            'icon' => 'heroicon-o-user-group',
            'rows' => [
                EntityViewPresenterSupport::row(
                    'السعة الاستيعابية',
                    EntityViewPresenterSupport::formatCapacity($opportunity->capacity, $opportunity->approvedRegistrationsCount()),
                    'heroicon-o-users',
                ),
                EntityViewPresenterSupport::row(
                    'الساعات المعتمدة',
                    self::formatApprovedHours($opportunity),
                    'heroicon-o-bolt',
                ),
            ],
        ];
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>}
     */
    private static function teamSection(VolunteerOpportunity $opportunity): array
    {
        return [
            'title' => 'الفريق',
            'icon' => 'heroicon-o-user-circle',
            'rows' => [
                EntityViewPresenterSupport::row('منسق الفرصة', self::resolveResponsibleName($opportunity), 'heroicon-o-user-circle'),
            ],
        ];
    }

    /**
     * @return array{title: string, icon: string, prose: string}|null
     */
    private static function descriptionSection(VolunteerOpportunity $opportunity): ?array
    {
        if (blank($opportunity->description)) {
            return null;
        }

        return EntityViewPresenterSupport::proseSection(
            'نبذة عن الفرصة',
            'heroicon-o-document-text',
            (string) $opportunity->description,
        );
    }

    private static function registrationLabel(VolunteerOpportunity $opportunity): string
    {
        if ($opportunity->status !== OpportunityStatus::Published) {
            return 'مغلق';
        }

        $today = Carbon::today();

        if ($opportunity->end_date !== null && $opportunity->end_date->lt($today)) {
            return 'منتهي';
        }

        if ($opportunity->start_date !== null && $opportunity->start_date->gt($today)) {
            return 'لم يبدأ';
        }

        if (! $opportunity->hasCapacity()) {
            return 'ممتلئ';
        }

        return 'مفتوح';
    }

    private static function durationDescription(VolunteerOpportunity $opportunity): string
    {
        if ($opportunity->start_date === null || $opportunity->end_date === null) {
            return 'غير محدد';
        }

        $days = max(1, (int) $opportunity->start_date->diffInDays($opportunity->end_date) + 1);

        return sprintf('%d يوماً', $days);
    }

    private static function formatApprovedHours(VolunteerOpportunity $opportunity): string
    {
        $hours = $opportunity->totalApprovedHours();

        return $hours > 0 ? number_format($hours, 1).' ساعة' : 'لا توجد ساعات بعد';
    }

    private static function resolveResponsibleName(VolunteerOpportunity $opportunity): string
    {
        if ($opportunity->assigned_to !== null && $opportunity->assignee instanceof User) {
            return $opportunity->assignee->name;
        }

        if ($opportunity->created_by !== null && $opportunity->creator instanceof User) {
            return $opportunity->creator->name;
        }

        return '—';
    }
}
