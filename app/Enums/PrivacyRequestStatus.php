<?php

namespace App\Enums;

enum PrivacyRequestStatus: string
{
    case Submitted = 'submitted';
    case IdentityVerificationRequired = 'identity_verification_required';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case PartiallyApproved = 'partially_approved';
    case Processing = 'processing';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'مُقدَّم',
            self::IdentityVerificationRequired => 'يتطلب تحققاً',
            self::UnderReview => 'قيد المراجعة',
            self::Approved => 'معتمد',
            self::PartiallyApproved => 'معتمد جزئياً',
            self::Processing => 'قيد المعالجة',
            self::Completed => 'مكتمل',
            self::Rejected => 'مرفوض',
            self::Cancelled => 'ملغى',
            self::Failed => 'فشل',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [
            self::Submitted,
            self::IdentityVerificationRequired,
            self::UnderReview,
            self::Approved,
            self::PartiallyApproved,
            self::Processing,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Rejected,
            self::Cancelled,
            self::Failed,
        ], true);
    }
}
