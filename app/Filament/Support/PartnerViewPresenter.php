<?php

namespace App\Filament\Support;

use App\Models\Partner;

final class PartnerViewPresenter
{
    /**
     * @return array{stats: array<int, array{label: string, value: string, icon: string}>, sections: array<int, array<string, mixed>>}
     */
    public static function present(Partner $partner): array
    {
        return [
            'stats' => self::stats($partner),
            'sections' => array_values(array_filter([
                self::overviewSection($partner),
                self::presenceSection($partner),
            ])),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, icon: string}>
     */
    private static function stats(Partner $partner): array
    {
        return [
            [
                'label' => 'الحالة',
                'value' => $partner->is_active ? 'نشط' : 'غير نشط',
                'icon' => 'heroicon-o-check-circle',
            ],
            [
                'label' => 'الترتيب',
                'value' => (string) $partner->sort_order,
                'icon' => 'heroicon-o-arrows-up-down',
            ],
            [
                'label' => 'النوع',
                'value' => filled($partner->type) ? (string) $partner->type : '—',
                'icon' => 'heroicon-o-building-office-2',
            ],
        ];
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>}
     */
    private static function overviewSection(Partner $partner): array
    {
        return [
            'title' => 'نظرة عامة',
            'icon' => 'heroicon-o-eye',
            'rows' => [
                EntityViewPresenterSupport::row(
                    'اسم الشريك',
                    $partner->name,
                    'heroicon-o-building-office-2',
                ),
                EntityViewPresenterSupport::row(
                    'نوع الشريك',
                    filled($partner->type) ? (string) $partner->type : '—',
                    'heroicon-o-tag',
                ),
                EntityViewPresenterSupport::row(
                    'الحالة',
                    $partner->is_active ? 'نشط في الموقع' : 'غير نشط',
                    'heroicon-o-signal',
                    EntityViewPresenterSupport::activeBadge((bool) $partner->is_active),
                ),
            ],
        ];
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>}|null
     */
    private static function presenceSection(Partner $partner): ?array
    {
        $rows = [
            EntityViewPresenterSupport::row(
                'ترتيب العرض',
                (string) $partner->sort_order,
                'heroicon-o-arrows-up-down',
            ),
            EntityViewPresenterSupport::row(
                'رابط الموقع',
                filled($partner->website_url) ? (string) $partner->website_url : '—',
                'heroicon-o-link',
            ),
            EntityViewPresenterSupport::row(
                'تاريخ الإضافة',
                EntityViewPresenterSupport::formatDate($partner->created_at),
                'heroicon-o-calendar-days',
            ),
        ];

        if ($partner->updated_at !== null && ! $partner->updated_at->equalTo($partner->created_at)) {
            $rows[] = EntityViewPresenterSupport::row(
                'آخر تحديث',
                EntityViewPresenterSupport::formatDate($partner->updated_at),
                'heroicon-o-pencil-square',
            );
        }

        return [
            'title' => 'الظهور والروابط',
            'icon' => 'heroicon-o-globe-alt',
            'rows' => $rows,
        ];
    }
}
