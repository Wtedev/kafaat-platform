<?php

namespace App\Enums;

use App\Models\LearningPath;
use App\Models\VolunteerOpportunity;

enum CertificateEligibilityMode: string
{
    case AttendanceOnly = 'attendance_only';
    case ScoreOnly = 'score_only';
    case Both = 'both';
    case Average = 'average';
    case CompletedAllCourses = 'completed_all_courses';
    case MinApprovedHours = 'min_approved_hours';

    /**
     * @return list<self>
     */
    public static function forOwner(string $ownerClass): array
    {
        return match ($ownerClass) {
            LearningPath::class => [
                self::CompletedAllCourses,
                self::AttendanceOnly,
                self::ScoreOnly,
                self::Both,
                self::Average,
            ],
            VolunteerOpportunity::class => [
                self::MinApprovedHours,
            ],
            default => [
                self::AttendanceOnly,
                self::ScoreOnly,
                self::Both,
                self::Average,
            ],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::AttendanceOnly => 'الحضور فقط',
            self::ScoreOnly => 'الدرجة فقط',
            self::Both => 'الحضور والدرجة معاً',
            self::Average => 'متوسط الحضور والدرجة',
            self::CompletedAllCourses => 'إكمال كل الدورات',
            self::MinApprovedHours => 'حد الساعات المعتمدة',
        };
    }
}
