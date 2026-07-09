<?php

namespace App\Filament\Support;

use App\Enums\ProgramStatus;
use App\Models\TrainingProgram;
use App\Support\TrainingProgramExtrasSupport;

final class TrainingProgramViewPresenter
{
    /**
     * @return array{stats: array<int, array{label: string, value: string, icon: string}>, sections: array<int, array<string, mixed>>}
     */
    public static function present(TrainingProgram $program): array
    {
        $standalone = $program->learning_path_id === null;

        return [
            'stats' => self::stats($program),
            'sections' => array_values(array_filter([
                self::overviewSection($program),
                $standalone ? self::scheduleSection($program) : null,
                $standalone ? self::enrollmentSection($program) : null,
                self::teamSection($program, $standalone),
                self::descriptionSection($program),
            ])),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, icon: string}>
     */
    private static function stats(TrainingProgram $program): array
    {
        return [
            [
                'label' => 'المسجّلون',
                'value' => (string) $program->totalRegistrationsCount(),
                'icon' => 'heroicon-o-users',
            ],
            [
                'label' => 'المقبولون',
                'value' => (string) $program->approvedRegistrationsCount(),
                'icon' => 'heroicon-o-check-circle',
            ],
            [
                'label' => 'المجتازون',
                'value' => (string) $program->completedRegistrationsCount(),
                'icon' => 'heroicon-o-academic-cap',
            ],
        ];
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>}
     */
    private static function overviewSection(TrainingProgram $program): array
    {
        $status = $program->status;
        $registrationLabel = $program->registrationWindowStatusLabel();

        return [
            'title' => 'نظرة عامة',
            'icon' => 'heroicon-o-eye',
            'field' => 'overview',
            'rows' => [
                EntityViewPresenterSupport::row(
                    'اسم البرنامج',
                    $program->title ?? '—',
                    'heroicon-o-book-open',
                ),
                EntityViewPresenterSupport::row(
                    'نوع البرنامج',
                    $program->program_kind?->label() ?? 'غير محدد',
                    'heroicon-o-book-open',
                ),
                EntityViewPresenterSupport::row(
                    'مسار الكفاءة',
                    $program->competency_track?->shortLabel() ?? '—',
                    'heroicon-o-squares-2x2',
                ),
                EntityViewPresenterSupport::row(
                    'طريقة التنفيذ',
                    $program->deliveryModeDescription() ?? '—',
                    'heroicon-o-map-pin',
                ),
                EntityViewPresenterSupport::row(
                    'التبعية للمسار',
                    $program->learning_path_id === null
                        ? 'برنامج مستقل'
                        : ($program->learningPath?->title ?? '—'),
                    'heroicon-o-map',
                ),
                EntityViewPresenterSupport::row(
                    'حالة النشر',
                    self::publicationStatusLabel($program),
                    'heroicon-o-signal',
                    EntityViewPresenterSupport::programStatusBadge($status),
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
    private static function scheduleSection(TrainingProgram $program): array
    {
        $rows = [
            EntityViewPresenterSupport::row(
                'مواعيد البرنامج',
                EntityViewPresenterSupport::formatDateRange($program->start_date, $program->end_date),
                'heroicon-o-calendar-days',
            ),
        ];

        if ($program->learning_path_id === null) {
            $rows[] = EntityViewPresenterSupport::row(
                'فترة التسجيل',
                self::formatRegistrationWindow($program),
                'heroicon-o-clock',
            );
        }

        $weekdaysLabel = self::formatProgramWeekdays($program);
        if ($weekdaysLabel !== null) {
            $rows[] = EntityViewPresenterSupport::row(
                'أيام البرنامج',
                $weekdaysLabel,
                'heroicon-o-calendar',
            );
        }

        $rows[] = EntityViewPresenterSupport::row(
            'مدة البرنامج',
            $program->programDurationDescription(),
            'heroicon-o-arrow-path',
        );

        if ($program->status !== ProgramStatus::Published) {
            $rows[] = TrainingEntityPublicationViewSupport::publicationDateRow(
                $program,
                ProgramStatus::Published,
                $program->status,
                $program->published_at,
            );
        }

        return [
            'title' => 'الجدول الزمني',
            'icon' => 'heroicon-o-calendar',
            'field' => 'schedule',
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>}
     */
    private static function enrollmentSection(TrainingProgram $program): array
    {
        return [
            'title' => 'التسجيل والسعة',
            'icon' => 'heroicon-o-user-group',
            'field' => 'enrollment',
            'rows' => [
                EntityViewPresenterSupport::row(
                    'السعة الاستيعابية',
                    EntityViewPresenterSupport::formatCapacity($program->capacity, $program->approvedRegistrationsCount()),
                    'heroicon-o-users',
                ),
                EntityViewPresenterSupport::row(
                    'قبول التسجيل',
                    $program->auto_accept_registrations ? 'تلقائي' : 'يدوي',
                    'heroicon-o-check-badge',
                    $program->auto_accept_registrations ? 'success' : 'gray',
                ),
            ],
        ];
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>}
     */
    private static function teamSection(TrainingProgram $program, bool $standalone): array
    {
        $responsible = self::resolveResponsibleName($program);
        $rows = [
            EntityViewPresenterSupport::row('المسؤول', $responsible, 'heroicon-o-user-circle'),
        ];

        if ($standalone) {
            $editors = $program->editors->pluck('name')->filter()->values()->all();

            if ($editors !== []) {
                $editorLabel = count($editors) === 1 && $editors[0] === $responsible
                    ? null
                    : implode('، ', $editors);

                if ($editorLabel !== null) {
                    $rows[] = EntityViewPresenterSupport::row('فريق العمل', $editorLabel, 'heroicon-o-users');
                }
            }
        }

        return [
            'title' => 'الفريق',
            'icon' => 'heroicon-o-user-circle',
            'field' => 'team',
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, icon: string, prose: string}|null
     */
    private static function descriptionSection(TrainingProgram $program): array
    {
        return EntityViewPresenterSupport::proseSection(
            'نبذة عن البرنامج',
            'heroicon-o-document-text',
            filled(TrainingProgramExtrasSupport::publicDescription($program))
                ? TrainingProgramExtrasSupport::publicDescription($program)
                : '—',
            'description',
        );
    }

    private static function resolveResponsibleName(TrainingProgram $program): string
    {
        if ($program->owner_id !== null && $program->owner !== null) {
            return $program->owner->name;
        }

        if ($program->created_by !== null && $program->creator !== null) {
            return $program->creator->name;
        }

        if ($program->assigned_to !== null && $program->assignee !== null) {
            return $program->assignee->name;
        }

        return '—';
    }

    private static function formatRegistrationWindow(TrainingProgram $program): string
    {
        if ($program->registration_start === null && $program->registration_end === null) {
            return 'غير محددة';
        }

        return EntityViewPresenterSupport::formatDateRange($program->registration_start, $program->registration_end);
    }

    private static function publicationStatusLabel(TrainingProgram $program): string
    {
        $status = $program->status;
        $label = $status?->label() ?? 'غير محدد';

        if ($status === ProgramStatus::Published && $program->published_at !== null) {
            return $label.' — '.EntityViewPresenterSupport::formatDate($program->published_at);
        }

        if (
            $status !== ProgramStatus::Published
            && $program->published_at !== null
            && $program->published_at->isFuture()
        ) {
            return 'مجدول — '.EntityViewPresenterSupport::formatDate($program->published_at);
        }

        return $label;
    }

  /**
   * @return array<int, string>
   */
    private static function weekdayLabels(): array
    {
        return ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    }

    private static function formatProgramWeekdays(TrainingProgram $program): ?string
    {
        $weekdays = is_array($program->weekdays) ? $program->weekdays : [];

        if ($weekdays === []) {
            return null;
        }

        $labels = self::weekdayLabels();

        return collect($weekdays)
            ->map(fn ($day): string => $labels[(int) $day] ?? (string) $day)
            ->implode('، ');
    }
}
