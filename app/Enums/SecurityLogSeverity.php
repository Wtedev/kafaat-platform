<?php

namespace App\Enums;

enum SecurityLogSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';
}
