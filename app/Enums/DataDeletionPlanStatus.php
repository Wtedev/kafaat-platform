<?php

namespace App\Enums;

enum DataDeletionPlanStatus: string
{
    case Draft = 'draft';
    case ReadyForReview = 'ready_for_review';
    case Approved = 'approved';
    case Executing = 'executing';
    case Completed = 'completed';
    case PartiallyCompleted = 'partially_completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
