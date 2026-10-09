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

    public static function logoUrl(): string
    {
        $base = rtrim((string) config('site.website_url'), '/');
        $path = ltrim((string) config('brand.logos.kafaat_mail'), '/');

        return $base.'/'.$path;
    }

    public static function telegramUrlFor(?ProfileGender $gender): ?string
    {
        $program = TrainingProgram::query()->where('slug', self::SLUG)->first();

        if ($program === null || ! $program->whatsapp_groups_enabled) {
            return null;
        }

        return match ($gender) {
            ProfileGender::Male => TrainingProgramExtrasSupport::httpsGroupUrl($program->whatsapp_group_male),
            ProfileGender::Female => TrainingProgramExtrasSupport::httpsGroupUrl($program->whatsapp_group_female),
            default => null,
        };
    }
}
