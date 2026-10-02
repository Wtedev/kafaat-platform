<?php

namespace Tests\Unit\Certificates;

use App\Data\Certificates\EligibilityRules;
use App\Enums\CertificateEligibilityMode;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EligibilityRulesTest extends TestCase
{
    public function test_both_mode_requires_attendance_and_score_limits(): void
    {
        try {
            EligibilityRules::fromArray([
                'mode' => CertificateEligibilityMode::Both->value,
                'min_attendance' => 80,
            ]);
            $this->fail('كان يجب رفض نمط both بلا حد للدرجة.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('min_score', $exception->errors());
        }
    }

    public function test_score_only_rejects_an_attendance_limit(): void
    {
        try {
            EligibilityRules::fromArray([
                'mode' => CertificateEligibilityMode::ScoreOnly->value,
                'min_score' => 60,
                'min_attendance' => 80,
            ]);
            $this->fail('كان يجب رفض حد الحضور في نمط الدرجة فقط.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('min_attendance', $exception->errors());
        }
    }

    public function test_score_only_requires_min_score(): void
    {
        $this->expectException(ValidationException::class);

        EligibilityRules::fromArray([
            'mode' => CertificateEligibilityMode::ScoreOnly->value,
        ]);
    }

    public function test_attendance_only_accepts_an_attendance_limit_without_a_score(): void
    {
        $rules = EligibilityRules::fromArray([
            'mode' => CertificateEligibilityMode::AttendanceOnly->value,
            'min_attendance' => 80,
        ]);

        $this->assertSame(80.0, $rules->minAttendance);
        $this->assertNull($rules->minScore);
        $this->assertTrue($rules->requireCompletedStatus);
        $this->assertFalse($rules->requireActivityEnded);
    }

    public function test_average_mode_requires_min_average(): void
    {
        $rules = EligibilityRules::legacyProgramAverage();

        $this->assertSame(CertificateEligibilityMode::Average, $rules->mode);
        $this->assertSame(75.0, $rules->minAverage);
    }

    public function test_completed_all_courses_rejects_numeric_limits(): void
    {
        $rules = EligibilityRules::legacyPathCourses();

        $this->assertSame(CertificateEligibilityMode::CompletedAllCourses, $rules->mode);
        $this->assertStringContainsString('أكمل كل دورات المسار', $rules->summarySentence());

        $this->expectException(ValidationException::class);
        EligibilityRules::fromArray([
            'mode' => CertificateEligibilityMode::CompletedAllCourses->value,
            'min_score' => 60,
        ]);
    }

    public function test_min_approved_hours_requires_the_hours_limit(): void
    {
        $rules = EligibilityRules::fromArray([
            'mode' => CertificateEligibilityMode::MinApprovedHours->value,
            'min_approved_hours' => 12.5,
        ]);

        $this->assertSame(12.5, $rules->minApprovedHours);
        $this->assertStringContainsString('12.5', $rules->summarySentence());

        $this->expectException(ValidationException::class);
        EligibilityRules::fromArray([
            'mode' => CertificateEligibilityMode::MinApprovedHours->value,
        ]);
    }
}
