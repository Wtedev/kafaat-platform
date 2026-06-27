<?php

namespace App\Filament\Support;

use App\Enums\LearningPathKind;
use App\Enums\PathStatus;
use App\Models\LearningPath;
use App\Models\User;

final class LearningPathViewPresenter
{
    /**
     * @return array{stats: array<int, array{label: string, value: string, icon: string}>, sections: array<int, array<string, mixed>>}
     */
    public static function present(LearningPath $path): array
    {
        return [
            'stats' => self::stats($path),
            'sections' => array_values(array_filter([
                self::overviewSection($path),
                self::enrollmentSection($path),
                self::scheduleSection($path),
                self::teamSection($path),
                self::descriptionSection($path),
            ])),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, icon: string}>
     */
    private static function stats(LearningPath $path): array
    {
        return [
            [
                'label' => 'البرامج',
                'value' => (string) ($path->programs_count ?? $path->programs()->count()),
                'icon' => 'heroicon-o-academic-cap',
            ],
            [
                'label' => 'المسجّلون',
                'value' => (string) ($path->registrations_count ?? $path->registrations()->count()),
                'icon' => 'heroicon-o-users',
            ],
            [
                'label' => 'المجتازون',
                'value' => (string) ($path->completed_path_registrations_count ?? $path->completedPathRegistrations()->count()),
                'icon' => 'heroicon-o-check-badge',
            ],
        ];
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>}
     */
    private static function overviewSection(LearningPath $path): array
    {
        $registrationLabel = self::registrationLabel($path);

        return [
            'title' => 'نظرة عامة',
            'icon' => 'heroicon-o-eye',
            'field' => 'overview',
            'rows' => [
                EntityViewPresenterSupport::row(
                    'اسم المسار',
                    $path->title ?? '—',
                    'heroicon-o-map',
                ),
                EntityViewPresenterSupport::row(
                    'حالة المسار',
                    $path->status?->label() ?? 'غير محدد',
                    'heroicon-o-signal',
                    EntityViewPresenterSupport::pathStatusBadge($path->status),
                ),
                EntityViewPresenterSupport::row(
                    'حالة التسجيل',
                    $registrationLabel,
                    'heroicon-o-clipboard-document-check',
                    EntityViewPresenterSupport::registrationWindowBadge($registrationLabel),
                ),
                EntityViewPresenterSupport::row(
                    'نوع المسار',
                    $path->path_kind?->label() ?? 'غير محدد',
                    'heroicon-o-map',
                ),
            ],
        ];
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>}
     */
    private static function enrollmentSection(LearningPath $path): array
    {
        return [
            'title' => 'التسجيل والسعة',
            'icon' => 'heroicon-o-user-group',
            'field' => 'enrollment',
            'rows' => [
                EntityViewPresenterSupport::row(
                    'السعة الاستيعابية',
                    EntityViewPresenterSupport::formatCapacity($path->capacity, $path->approvedRegistrationsCount()),
                    'heroicon-o-users',
                ),
                EntityViewPresenterSupport::row(
                    'قبول التسجيل',
                    $path->auto_accept_registrations ? 'تلقائي' : 'يدوي',
                    'heroicon-o-check-badge',
                    $path->auto_accept_registrations ? 'success' : 'gray',
                ),
            ],
        ];
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>}
     */
    private static function scheduleSection(LearningPath $path): array
    {
        $section = [
            'title' => 'الجدول الزمني',
            'icon' => 'heroicon-o-calendar',
            'rows' => [
                TrainingEntityPublicationViewSupport::publicationDateRow(
                    $path,
                    PathStatus::Published,
                    $path->status,
                    $path->published_at,
                ),
            ],
        ];

        if (
            $path->status !== PathStatus::Published
            && TrainingEntityFormSupport::publishControlsVisibleForRecord($path, PathStatus::Published)
        ) {
            $section['field'] = 'schedule';
        }

        return $section;
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>}
     */
    private static function teamSection(LearningPath $path): array
    {
        $responsible = self::resolveResponsibleName($path);
        $rows = [
            EntityViewPresenterSupport::row('المسؤول', $responsible, 'heroicon-o-user-circle'),
        ];

        $editors = $path->relationLoaded('editors')
            ? $path->editors->pluck('name')->filter()->values()->all()
            : [];

        if ($editors !== []) {
            $editorLabel = count($editors) === 1 && $editors[0] === $responsible
                ? null
                : implode('، ', $editors);

            if ($editorLabel !== null) {
                $rows[] = EntityViewPresenterSupport::row('فريق العمل', $editorLabel, 'heroicon-o-users');
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
    private static function descriptionSection(LearningPath $path): array
    {
        return EntityViewPresenterSupport::proseSection(
            'نبذة عن المسار',
            'heroicon-o-document-text',
            filled($path->description) ? (string) $path->description : '—',
            'description',
        );
    }

    private static function registrationLabel(LearningPath $path): string
    {
        if ($path->status !== PathStatus::Published) {
            return 'مغلق';
        }

        if (! $path->hasCapacity()) {
            return 'ممتلئ';
        }

        return 'مفتوح';
    }

    private static function resolveResponsibleName(LearningPath $path): string
    {
        if ($path->owner_id !== null && $path->owner instanceof User) {
            return $path->owner->name;
        }

        if ($path->created_by !== null && $path->creator instanceof User) {
            return $path->creator->name;
        }

        return '—';
    }
}
