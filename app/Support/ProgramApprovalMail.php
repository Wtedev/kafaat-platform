<?php

namespace App\Support;

use App\Enums\ProfileGender;
use App\Models\TrainingProgram;
use App\Models\User;

final class ProgramApprovalMail
{
    public const FORUM_SUBJECT = 'قبولك في الهاكاثون – ملتقى تحليل البيانات في القطاع غير الربحي';

    public const FORUM_INBOX = 'مبارك قبولك في هاكاثون تحليل البيانات 🎉 دليل الهاكاثون ورابط البحث عن فريق في بريدك الإلكتروني.';

    public const PENDING_LINE = 'سيتم تزويدك برابط المجموعة قريباً';

    public const BUTTON_LABEL = 'الانضمام إلى مجموعة البرنامج';

    public const GROUP_MARKER = '<!--approval-group-->';

    public const FORUM_BODY = <<<'HTML'
<p style="margin:0 0 16px;text-align:center;">رسالة من فريق كفاءات بخصوص ملتقى تحليل البيانات في القطاع غير الربحي – النسخة الثانية.</p>
<p style="margin:0 0 16px;text-align:center;">يسر جمعية كفاءات الأهلية لبناء قدرات الشباب أن تهنئك بقبولك في هاكاثون تحليل البيانات، المرحلة التطبيقية من الملتقى، بعد اختتام المرحلة النظرية بنجاح.</p>
<p style="margin:16px 0 0;text-align:center;"><strong>دليل الهاكاثون</strong></p>
<table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:8px auto 16px;"><tr><td align="center" bgcolor="#1a9399" style="border-radius:4px;background-color:#1a9399;"><a href="https://drive.google.com/file/d/1XjBjiYW_UCdVuZqicMA3O23pdpHbqdte/view?usp=drivesdk" target="_blank" rel="noopener" style="display:inline-block;padding:12px 18px;color:#ffffff;text-decoration:none;font-weight:bold;">دليل الهاكاثون</a></td></tr></table>
<p style="margin:16px 0 0;text-align:center;"><strong>ابحث عن فريق</strong></p>
<table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:8px auto 16px;"><tr><td align="center" bgcolor="#1a9399" style="border-radius:4px;background-color:#1a9399;"><a href="https://t.me/+BS-rRPh5I8wwZjM8" target="_blank" rel="noopener" style="display:inline-block;padding:12px 18px;color:#ffffff;text-decoration:none;font-weight:bold;">ابحث عن فريق</a></td></tr></table>
<p style="margin:16px 0 0;text-align:center;"><strong>رابط طلب الاستشارات</strong></p>
<table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:8px auto 16px;"><tr><td align="center" bgcolor="#1a9399" style="border-radius:4px;background-color:#1a9399;"><a href="https://web-production-ade28.up.railway.app" target="_blank" rel="noopener" style="display:inline-block;padding:12px 18px;color:#ffffff;text-decoration:none;font-weight:bold;">طلب الاستشارات</a></td></tr></table>
<p style="margin:8px 0 0;text-align:center;"><strong>تنويه:</strong></p>
<p style="margin:0 0 4px;text-align:center;">نأمل منك الانضمام إلى مجموعة الملتقى على تيليجرام إن لم تكن قد انضممت بعد، لمتابعة التنبيهات والتحديثات الخاصة بالهاكاثون.</p>
<!--approval-group-->
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
            return self::FORUM_INBOX;
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
