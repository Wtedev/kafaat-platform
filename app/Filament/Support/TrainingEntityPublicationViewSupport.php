<?php

namespace App\Filament\Support;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

final class TrainingEntityPublicationViewSupport
{
    /**
     * @return array<string, mixed>
     */
    public static function publicationDateRow(
        Model $record,
        BackedEnum $publishedStatus,
        ?BackedEnum $currentStatus,
        ?Carbon $publishedAt,
    ): array {
        if ($currentStatus === $publishedStatus) {
            return array_merge(
                EntityViewPresenterSupport::row(
                    'تاريخ النشر',
                    EntityViewPresenterSupport::formatDate($publishedAt),
                    'heroicon-o-globe-alt',
                ),
                [
                    'companion_badge' => 'منشور',
                    'companion_badge_tone' => 'success',
                ],
            );
        }

        if (! TrainingEntityFormSupport::publishControlsVisibleForRecord($record, $publishedStatus)) {
            return EntityViewPresenterSupport::row(
                'موعد النشر',
                '—',
                'heroicon-o-calendar',
            );
        }

        $isScheduled = $publishedAt !== null && $publishedAt->isFuture();
        $value = $isScheduled
            ? EntityViewPresenterSupport::formatDate($publishedAt)
            : 'غير مجدول';

        return array_merge(
            EntityViewPresenterSupport::row(
                'موعد النشر',
                $value,
                'heroicon-o-calendar',
                $isScheduled ? 'warning' : 'gray',
            ),
            [
                'row_actions' => [
                    ['action' => 'publishEntityNow', 'label' => 'نشر الآن'],
                ],
            ],
        );
    }
}
