<?php

namespace App\Enums;

enum DeletionResourceAction: string
{
    case Delete = 'delete';
    case Anonymize = 'anonymize';
    case RetainRestricted = 'retain_restricted';
    case SkipDueToRetentionException = 'skip_due_to_retention_exception';
}
