<?php

namespace App\Enums;

enum PrivacyRequestEventType: string
{
    case Submitted = 'submitted';
    case IdentityVerified = 'identity_verified';
    case Assigned = 'assigned';
    case ReviewStarted = 'review_started';
    case Approved = 'approved';
    case PartiallyApproved = 'partially_approved';
    case Rejected = 'rejected';
    case AccessResponseCreated = 'access_response_created';
    case CorrectionApplied = 'correction_applied';
    case ProcessingStarted = 'processing_started';
    case DeletionPlanCreated = 'deletion_plan_created';
    case DeletionExecuted = 'deletion_executed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
}
