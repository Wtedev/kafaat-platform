<?php

namespace App\Enums;

enum SecurityLogResult: string
{
    case Success = 'success';
    case Failed = 'failed';
    case Denied = 'denied';
    case Blocked = 'blocked';
}
