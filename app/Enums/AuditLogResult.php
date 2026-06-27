<?php

namespace App\Enums;

enum AuditLogResult: string
{
    case Success = 'success';
    case Failure = 'failure';
    case Denied = 'denied';
}
