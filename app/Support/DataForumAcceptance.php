<?php

namespace App\Support;

use App\Enums\ProfileGender;
use App\Models\TrainingProgram;

final class DataForumAcceptance
{
    public const SLUG = 'multaqa-tahlil-al-bayanat-2';

    public const SUBJECT = 'قبولك النهائي في ملتقى تحليل البيانات – النسخة الثانية';

    public const INBOX_MESSAGE = 'مبارك قبولك النهائي في ملتقى تحليل البيانات. تفاصيل الملتقى ورابط مجموعة تيليجرام في بريدك الإلكتروني.';

    public const TELEGRAM_PENDING_LINE = 'سيتم تزويدك برابط مجموعة الملتقى قريباً.';

    public static function matches(?TrainingProgram $program): bool
    {
        return $program !== null && $program->slug === self::SLUG;
    }

    public static function telegramUrlFor(?ProfileGender $gender): ?string
    {
        $url = match ($gender) {
            ProfileGender::Male => config('data_forum.telegram_male'),
            ProfileGender::Female => config('data_forum.telegram_female'),
            default => null,
        };

        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        return $url !== '' ? $url : null;
    }
}
