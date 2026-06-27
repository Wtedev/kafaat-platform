<?php

namespace App\Enums;

enum RetentionPolicyAction: string
{
    case Delete = 'delete';
    case Anonymize = 'anonymize';
    case RetainRestricted = 'retain_restricted';
}
