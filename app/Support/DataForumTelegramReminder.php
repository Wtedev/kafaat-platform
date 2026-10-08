<?php

namespace App\Support;

use App\Enums\RegistrationStatus;
use App\Models\ProgramRegistration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class DataForumTelegramReminder
{
    public const SUBJECT = 'تذكير بالانضمام إلى المجموعة الخاصة بملتقى تحليل البيانات قبل الانطلاق';

    public const TEMPLATE_KEY = 'data_forum.telegram_reminder';

    public const WHEN_LATER = 'بعد غدٍ';

    public const WHEN_FRIDAY = 'غداً';

    public static function whenWord(?Carbon $at = null): string
    {
        $moment = ($at ?? now())->timezone('Asia/Riyadh');

        return $moment->isFriday() ? self::WHEN_FRIDAY : self::WHEN_LATER;
    }

    public static function cutoff(): Carbon
    {
        return Carbon::parse('2026-10-08 13:10:59', 'Asia/Riyadh');
    }

    /**
     * Approved registrants of the data forum at or before the first-batch cutoff.
     *
     * @return Builder<ProgramRegistration>
     */
    public static function cohortQuery(): Builder
    {
        return ProgramRegistration::query()
            ->where('status', RegistrationStatus::Approved)
            ->where('approved_at', '<=', self::cutoff())
            ->whereHas('trainingProgram', function (Builder $program): void {
                $program->where('slug', DataForumAcceptance::SLUG);
            });
    }
}
