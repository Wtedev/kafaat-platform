<?php

namespace App\Services\Certificates;

use App\Data\Certificates\EligibilityRules;
use App\Enums\CertificateEligibilityMode;

class CertificateProgressCopy
{
    public function line(EligibilityRules $rules, ?float $attendance, ?float $score): string
    {
        $attendanceText = $attendance === null ? 'غير مرصود' : $this->number($attendance).'%';
        $scoreText = $score === null ? 'غير مرصودة' : $this->number($score);

        return match ($rules->mode) {
            CertificateEligibilityMode::AttendanceOnly => 'حضورك '.$attendanceText.' — المطلوب '.$this->number((float) $rules->minAttendance).'%',
            CertificateEligibilityMode::ScoreOnly => 'درجتك '.$scoreText.' — المطلوب '.$this->number((float) $rules->minScore),
            CertificateEligibilityMode::Both => 'حضورك '.$attendanceText.' — المطلوب '.$this->number((float) $rules->minAttendance).'% ، ودرجتك '.$scoreText.' — المطلوب '.$this->number((float) $rules->minScore),
            CertificateEligibilityMode::Average => 'متوسطك '.$this->averageText($attendance, $score).' — المطلوب '.$this->number((float) $rules->minAverage).'%',
            CertificateEligibilityMode::CompletedAllCourses => 'يُشترط إكمال كل دورات المسار',
            CertificateEligibilityMode::MinApprovedHours => 'ساعاتك المعتمدة — المطلوب '.$this->number((float) $rules->minApprovedHours),
        };
    }

    private function averageText(?float $attendance, ?float $score): string
    {
        if ($attendance === null || $score === null) {
            return 'غير مكتمل';
        }

        return $this->number(round(($attendance + $score) / 2, 2)).'%';
    }

    private function number(float $value): string
    {
        $rounded = round($value, 2);

        if (abs($rounded - round($rounded)) < 0.001) {
            return (string) (int) round($rounded);
        }

        return number_format($rounded, 2, '.', '');
    }
}
