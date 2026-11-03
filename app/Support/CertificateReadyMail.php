<?php

namespace App\Support;

final class CertificateReadyMail
{
    public const SUBJECT = 'شهادتك جاهزة | جمعية كفاءات الأهلية';

    public const BUTTON_LABEL = 'عرض الشهادة';

    /**
     * Staff copies of every program certificate email (BCC).
     * لمى المشيقح + وجدان M M M as named on production.
     *
     * @var list<string>
     */
    public const COPY_EMAILS = [
        'lamaalmushyqih@gmail.com',
        'wejdanalsmanii@gmail.com',
    ];

    /**
     * @return list<string>
     */
    public static function copyEmailsExcept(?string $recipientEmail): array
    {
        $recipient = strtolower(trim((string) $recipientEmail));

        return array_values(array_filter(
            self::COPY_EMAILS,
            fn (string $email): bool => $email !== '' && strtolower($email) !== $recipient,
        ));
    }
}
