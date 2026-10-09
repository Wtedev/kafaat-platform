<?php

namespace App\Support;

use App\Enums\ProfileGender;
use App\Models\TrainingProgram;
use App\Models\User;

final class ProgramApprovalMail
{
    public const FORUM_SUBJECT = 'قبولك النهائي في ملتقى تحليل البيانات – النسخة الثانية';

    public const PENDING_LINE = 'سيتم تزويدك برابط المجموعة قريباً';

    public const BUTTON_LABEL = 'الانضمام إلى مجموعة البرنامج';

    public const FORUM_BODY = <<<'HTML'
<p style="margin:0 0 16px;text-align:center;">رسالة من فريق كفاءات بخصوص ملتقى تحليل البيانات في القطاع غير الربحي – النسخة الثانية.</p>
<p style="margin:0 0 16px;text-align:center;">يسر جمعية كفاءات الأهلية لبناء قدرات الشباب أن تتقدم لك بخالص التهنئة بمناسبة <strong>قبولك النهائي في الملتقى</strong>.</p>
<p style="margin:0 0 8px;text-align:center;"><strong>تفاصيل الملتقى:</strong></p>
<p style="margin:0 0 8px;text-align:center;"><strong>تاريخ الانطلاق:</strong> 10 أكتوبر 2026م</p>
<p style="margin:0 0 8px;text-align:center;"><strong>نوع اللقاء:</strong> عن بُعد عبر منصة Zoom</p>
<p style="margin:0 0 16px;text-align:center;"><strong>مواعيد اللقاء:</strong> تُقام المرحلة النظرية من 10 إلى 12 أكتوبر، من الساعة 4 مساءً حتى الساعة 7 مساءً. أما مواعيد المراحل اللاحقة فسيتم تزويد المشاركين بها تباعًا وفق سير الملتقى.</p>
<p style="margin:0 0 8px;text-align:center;"><strong>تنويه:</strong></p>
<p style="margin:0 0 16px;text-align:center;">نأمل منك الانضمام إلى مجموعة الملتقى على تيليجرام لمتابعة التنبيهات والتحديثات وروابط اللقاءات والمعلومات المتعلقة بمراحل الملتقى.</p>
<p style="margin:0 0 16px;text-align:center;">سعداء بانضمامك، ونتطلع إلى مشاركتك في <strong>رحلة</strong> <strong>تبدأ بالبيانات.. وتمتد إلى ما وراء الأرقام.</strong></p>
<p style="margin:0;text-align:center;">مع تحيات فريق جمعية كفاءات الأهلية لبناء قدرات الشباب</p>
HTML;

    public static function subjectFor(TrainingProgram $program): string
    {
        if (self::isForumMessage($program)) {
            return self::FORUM_SUBJECT;
        }

        return 'تم قبول تسجيلك — '.$program->title;
    }

    public static function isForumMessage(TrainingProgram $program): bool
    {
        return str_contains((string) $program->approval_message, 'ملتقى تحليل البيانات');
    }

    public static function inboxMessage(TrainingProgram $program): string
    {
        if (self::isForumMessage($program)) {
            return 'مبارك قبولك النهائي في ملتقى تحليل البيانات. تفاصيل الملتقى ورابط مجموعة تيليجرام في بريدك الإلكتروني.';
        }

        if (filled($program->approval_message)) {
            $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $program->approval_message)) ?? '');

            if ($plain !== '') {
                return $plain;
            }
        }

        return 'تم قبول طلبك في البرنامج التدريبي «'.$program->title.'».';
    }

    public static function groupUrl(TrainingProgram $program, ?User $user): ?string
    {
        if (! $program->whatsapp_groups_enabled || ! $user instanceof User) {
            return null;
        }

        $gender = $user->profile?->gender;

        return match ($gender) {
            ProfileGender::Male => TrainingProgramExtrasSupport::httpsGroupUrl($program->whatsapp_group_male),
            ProfileGender::Female => TrainingProgramExtrasSupport::httpsGroupUrl($program->whatsapp_group_female),
            default => null,
        };
    }

    public static function genderUnspecified(?User $user): bool
    {
        if (! $user instanceof User) {
            return true;
        }

        return ! $user->profile?->gender instanceof ProfileGender;
    }
}
