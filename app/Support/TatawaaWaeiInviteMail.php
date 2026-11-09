<?php

namespace App\Support;

final class TatawaaWaeiInviteMail
{
    public const SUBJECT = 'مبادرة تدريبية | رحلتك في كتابة محتوى يتردد صداه';

    public const REMINDER_SUBJECT = 'تذكير | مبادرة تدريبية | رحلتك في كتابة محتوى يتردد صداه';

    public const TEMPLATE_KEY = 'tatawaa_waei.registration_invite';

    public const REMINDER_TEMPLATE_KEY = 'tatawaa_waei.registration_invite_reminder';

    public const BUTTON_LABEL = 'التسجيل في مبادرة تطوع واعي';

    public const HERO_PUBLIC_PATH = 'images/mail/tatawaa-waei-invite.jpg';

    /** Content-ID used for Resend/SMTP inline image (without angle brackets). */
    public const HERO_CONTENT_ID = 'tatawaa-waei-invite';

    /** Recipients JSON on the app storage disk (not committed — PII). */
    public const RECIPIENTS_STORAGE_PATH = 'tatawaa-waei/invite-recipients.json';

    public const PROGRAM_SLUG = 'tatawaa-waei';

    /** Keep spare Resend capacity for registration OTP / login. */
    public const QUOTA_RESERVE = 50;

    public static function registerUrl(): string
    {
        return rtrim((string) config('site.website_url'), '/').'/programs/tatawaa-waei';
    }

    public static function heroPublicUrl(): string
    {
        return rtrim((string) config('site.website_url'), '/').'/'.ltrim(self::HERO_PUBLIC_PATH, '/');
    }

    public static function heroCidUrl(): string
    {
        return 'cid:'.self::HERO_CONTENT_ID;
    }

    public static function heroAbsolutePath(): ?string
    {
        $path = public_path(self::HERO_PUBLIC_PATH);

        return is_file($path) ? $path : null;
    }

    /**
     * First word of a full name (Arabic or Latin), trimmed of punctuation.
     */
    public static function firstName(?string $fullName): string
    {
        $fullName = trim(preg_replace('/\s+/u', ' ', (string) $fullName) ?? '');
        if ($fullName === '') {
            return '';
        }

        $parts = preg_split('/\s+/u', $fullName) ?: [];
        // Do not pass Arabic punctuation into trim()'s mask — PHP trims by byte,
        // so a UTF-8 character like «،» (D8 8C) would strip the leading D8 of «إ».
        $first = preg_replace('/^[\s\x{060C},.\-]+|[\s\x{060C},.\-]+$/u', '', (string) ($parts[0] ?? '')) ?? '';

        return $first;
    }

    public static function openingLine(?string $fullName, bool $reminder = false): string
    {
        $first = self::firstName($fullName);
        $core = $reminder ? 'تذكير: اخترناك بين الكثير ..' : 'اخترناك بين الكثير ..';

        return $first !== ''
            ? $first.' '.$core
            : $core;
    }

    public static function subject(bool $reminder = false): string
    {
        return $reminder ? self::REMINDER_SUBJECT : self::SUBJECT;
    }

    public static function templateKey(bool $reminder = false): string
    {
        return $reminder ? self::REMINDER_TEMPLATE_KEY : self::TEMPLATE_KEY;
    }
}
