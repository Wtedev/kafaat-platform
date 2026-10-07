<?php

namespace App\Enums;

enum CertificateElementType: string
{
    case Field = 'field';
    case Text = 'text';
    case Qr = 'qr';
    case Image = 'image';
}
