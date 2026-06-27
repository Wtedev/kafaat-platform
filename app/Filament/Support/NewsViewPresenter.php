<?php

namespace App\Filament\Support;

use App\Models\News;

final class NewsViewPresenter
{
    /**
     * @return array{stats: array<int, array{label: string, value: string, icon: string}>, sections: array<int, array<string, mixed>>}
     */
    public static function present(News $news): array
    {
        return [
            'stats' => self::stats($news),
            'sections' => array_values(array_filter([
                self::overviewSection($news),
                self::datesSection($news),
                self::excerptSection($news),
                self::contentSection($news),
            ])),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, icon: string}>
     */
    private static function stats(News $news): array
    {
        return [
            [
                'label' => 'الحالة',
                'value' => $news->publicationStatusLabel(),
                'icon' => 'heroicon-o-signal',
            ],
            [
                'label' => 'التصنيف',
                'value' => filled($news->category) ? (string) $news->category : '—',
                'icon' => 'heroicon-o-tag',
            ],
            [
                'label' => 'آخر تحديث',
                'value' => $news->adminRelativeTime($news->updated_at),
                'icon' => 'heroicon-o-arrow-path',
            ],
        ];
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>}
     */
    private static function overviewSection(News $news): array
    {
        $statusLabel = $news->publicationStatusLabel();

        return [
            'title' => 'نظرة عامة',
            'icon' => 'heroicon-o-eye',
            'rows' => [
                EntityViewPresenterSupport::row(
                    'حالة النشر',
                    $statusLabel,
                    'heroicon-o-signal',
                    EntityViewPresenterSupport::newsPublicationBadge($statusLabel),
                ),
                EntityViewPresenterSupport::row(
                    'موعد الظهور',
                    $news->adminVisibilitySummary(),
                    'heroicon-o-globe-alt',
                ),
                EntityViewPresenterSupport::row(
                    'التصنيف',
                    filled($news->category) ? (string) $news->category : '—',
                    'heroicon-o-tag',
                ),
                EntityViewPresenterSupport::row(
                    'إشعارات المستفيدين',
                    $news->notify_audience_on_publish ? 'مفعّلة' : 'معطّلة',
                    'heroicon-o-bell',
                    $news->notify_audience_on_publish ? 'success' : 'gray',
                ),
                EntityViewPresenterSupport::row(
                    'تنبيه الوارد',
                    $news->notificationStatusSummary(),
                    'heroicon-o-inbox',
                ),
            ],
        ];
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>}
     */
    private static function datesSection(News $news): array
    {
        return [
            'title' => 'التواريخ',
            'icon' => 'heroicon-o-calendar',
            'rows' => [
                EntityViewPresenterSupport::row(
                    'تاريخ الإنشاء',
                    EntityViewPresenterSupport::formatDateTime($news->created_at),
                    'heroicon-o-plus-circle',
                ),
                EntityViewPresenterSupport::row(
                    'آخر تعديل',
                    EntityViewPresenterSupport::formatDateTime($news->updated_at),
                    'heroicon-o-pencil-square',
                ),
                EntityViewPresenterSupport::row(
                    'تاريخ النشر',
                    $news->isDraft()
                        ? 'لم يُنشر بعد'
                        : EntityViewPresenterSupport::formatDateTime($news->published_at),
                    'heroicon-o-clock',
                ),
            ],
        ];
    }

    /**
     * @return array{title: string, icon: string, prose: string}|null
     */
    private static function excerptSection(News $news): ?array
    {
        if (blank($news->excerpt)) {
            return null;
        }

        return EntityViewPresenterSupport::proseSection(
            'المقتطف',
            'heroicon-o-document-text',
            (string) $news->excerpt,
        );
    }

    /**
     * @return array{title: string, icon: string, prose: string}|null
     */
    private static function contentSection(News $news): ?array
    {
        if (blank($news->content)) {
            return null;
        }

        return EntityViewPresenterSupport::proseSection(
            'محتوى الخبر',
            'heroicon-o-newspaper',
            (string) $news->content,
        );
    }
}
