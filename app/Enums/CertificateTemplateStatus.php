<?php

namespace App\Enums;

enum CertificateTemplateStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Ready => 'جاهز',
        };
    }
}
