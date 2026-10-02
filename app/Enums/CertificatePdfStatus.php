<?php

namespace App\Enums;

enum CertificatePdfStatus: string
{
    case Pending = 'pending';
    case Generated = 'generated';
    case Failed = 'failed';
}
