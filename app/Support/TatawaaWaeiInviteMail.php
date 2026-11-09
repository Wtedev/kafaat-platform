<?php

namespace App\Support;

final class TatawaaWaeiInviteMail
{
    public const SUBJECT = 'دعوة للتسجيل | برنامج تطوع واعي';

    public const TEMPLATE_KEY = 'tatawaa_waei.registration_invite';

    public const BUTTON_LABEL = 'التسجيل في برنامج تطوع واعي';

    public const HERO_PUBLIC_PATH = 'images/mail/tatawaa-waei-invite.jpg';

    public static function registerUrl(): string
    {
        return rtrim((string) config('site.website_url'), '/').'/programs/tatawaa-waei';
    }

    public static function heroAbsolutePath(): ?string
    {
        $path = public_path(self::HERO_PUBLIC_PATH);

        return is_file($path) ? $path : null;
    }
}
