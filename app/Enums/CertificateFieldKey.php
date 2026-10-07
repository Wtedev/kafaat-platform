<?php

namespace App\Enums;

use App\Models\Certificate;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
use App\Support\Format\LocaleFormat;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use IntlDateFormatter;

/**
 * حقول الشهادة المغلقة. لا يتضمن trainer_name لأن TrainingProgram لا يملك هذا الحقل.
 */
enum CertificateFieldKey: string
{
    case RecipientName = 'recipient_name';
    case ActivityTitle = 'activity_title';
    case IssueDateGregorian = 'issue_date_gregorian';
    case IssueDateHijri = 'issue_date_hijri';
    case CertificateNumber = 'certificate_number';
    case VerificationCode = 'verification_code';
    case AttendancePercentage = 'attendance_percentage';
    case Score = 'score';
    case ActivityStartDate = 'activity_start_date';
    case ActivityEndDate = 'activity_end_date';
    case ActivityHours = 'activity_hours';
    case CompletedCoursesCount = 'completed_courses_count';
    case ApprovedVolunteerHours = 'approved_volunteer_hours';

    public function label(): string
    {
        return match ($this) {
            self::RecipientName => 'اسم المستفيد',
            self::ActivityTitle => 'عنوان النشاط',
            self::IssueDateGregorian => 'تاريخ الإصدار (ميلادي)',
            self::IssueDateHijri => 'تاريخ الإصدار (هجري)',
            self::CertificateNumber => 'رقم الشهادة',
            self::VerificationCode => 'رمز التحقق',
            self::AttendancePercentage => 'نسبة الحضور',
            self::Score => 'الدرجة',
            self::ActivityStartDate => 'تاريخ بداية النشاط',
            self::ActivityEndDate => 'تاريخ نهاية النشاط',
            self::ActivityHours => 'ساعات النشاط',
            self::CompletedCoursesCount => 'عدد الدورات المكتملة',
            self::ApprovedVolunteerHours => 'الساعات التطوعية المعتمدة',
        };
    }

    public function sample(): string
    {
        return match ($this) {
            self::RecipientName => 'سارة بنت محمد العتيبي',
            self::ActivityTitle => 'برنامج مهارات التحليل',
            self::IssueDateGregorian => '2026/10/02',
            self::IssueDateHijri => '20 ربيع الآخر 1448',
            self::CertificateNumber => 'CERT-20261002-AB12CD34',
            self::VerificationCode => 'a1b2c3d4e5f67890',
            self::AttendancePercentage => '92%',
            self::Score => '88',
            self::ActivityStartDate => '2026/09/01',
            self::ActivityEndDate => '2026/09/30',
            self::ActivityHours => '18',
            self::CompletedCoursesCount => '3',
            self::ApprovedVolunteerHours => '24',
        };
    }

    /**
     * @return list<self>
     */
    public static function forOwner(string $ownerClass): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case): bool => $case->appliesTo($ownerClass),
        ));
    }

    public function appliesTo(string $ownerClass): bool
    {
        return match ($this) {
            self::CompletedCoursesCount => $ownerClass === LearningPath::class,
            self::ApprovedVolunteerHours => $ownerClass === VolunteerOpportunity::class,
            default => true,
        };
    }

    public function resolve(Certificate $certificate): string
    {
        $certificate->loadMissing(['user', 'certificateable']);

        return match ($this) {
            self::RecipientName => $certificate->user?->certificateName() ?? '',
            self::ActivityTitle => $this->activityTitle($certificate),
            self::IssueDateGregorian => $this->formatGregorian($certificate->issued_at),
            self::IssueDateHijri => $this->formatHijri($certificate->issued_at),
            self::CertificateNumber => (string) ($certificate->certificate_number ?? ''),
            self::VerificationCode => (string) ($certificate->verification_code ?? ''),
            self::AttendancePercentage => $this->formatOptionalNumber($this->registration($certificate)?->attendance_percentage),
            self::Score => $this->formatOptionalNumber($this->registration($certificate)?->score, suffix: ''),
            self::ActivityStartDate => $this->formatGregorian($this->activityDate($certificate, 'start_date')),
            self::ActivityEndDate => $this->formatGregorian($this->activityDate($certificate, 'end_date')),
            self::ActivityHours => '',
            self::CompletedCoursesCount => $this->completedCoursesCount($certificate),
            self::ApprovedVolunteerHours => $this->approvedVolunteerHours($certificate),
        };
    }

    private function activityTitle(Certificate $certificate): string
    {
        $entity = $certificate->certificateable;

        if ($entity instanceof TrainingProgram || $entity instanceof LearningPath || $entity instanceof VolunteerOpportunity) {
            return (string) $entity->title;
        }

        return '';
    }

    private function registration(Certificate $certificate): ?Model
    {
        $entity = $certificate->certificateable;

        if ($entity instanceof TrainingProgram) {
            return ProgramRegistration::query()
                ->where('user_id', $certificate->user_id)
                ->where('training_program_id', $entity->getKey())
                ->first();
        }

        if ($entity instanceof LearningPath) {
            return PathRegistration::query()
                ->where('user_id', $certificate->user_id)
                ->where('learning_path_id', $entity->getKey())
                ->first();
        }

        if ($entity instanceof VolunteerOpportunity) {
            return VolunteerRegistration::query()
                ->where('user_id', $certificate->user_id)
                ->where('opportunity_id', $entity->getKey())
                ->first();
        }

        return null;
    }

    private function completedCoursesCount(Certificate $certificate): string
    {
        $registration = $this->registration($certificate);
        $path = $certificate->certificateable;
        if (! $registration instanceof PathRegistration || ! $path instanceof LearningPath) {
            return '';
        }

        $programIds = $path->programs()->where('status', ProgramStatus::Published)->pluck('id');
        if ($programIds->isEmpty()) {
            return '0';
        }

        $completed = ProgramRegistration::query()
            ->where('user_id', $registration->user_id)
            ->whereIn('training_program_id', $programIds)
            ->where('status', RegistrationStatus::Completed->value)
            ->count();

        return (string) $completed;
    }

    private function approvedVolunteerHours(Certificate $certificate): string
    {
        $registration = $this->registration($certificate);
        if (! $registration instanceof VolunteerRegistration) {
            return '';
        }

        return $this->formatOptionalNumber($registration->getApprovedHours(), suffix: '');
    }

    private function activityDate(Certificate $certificate, string $attribute): ?DateTimeInterface
    {
        $entity = $certificate->certificateable;
        if (! $entity instanceof Model) {
            return null;
        }

        $value = $entity->getAttribute($attribute);

        return $value instanceof DateTimeInterface ? $value : null;
    }

    private function formatGregorian(DateTimeInterface|Carbon|null $value): string
    {
        if ($value === null) {
            return '';
        }

        return LocaleFormat::date($value, 'y/MM/dd');
    }

    private function formatHijri(DateTimeInterface|Carbon|null $value): string
    {
        if ($value === null) {
            return '';
        }

        $date = Carbon::parse($value)->timezone(config('app.timezone'));
        $formatter = new IntlDateFormatter(
            'ar_SA@calendar=islamic-umalqura;numbers=latn',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $date->getTimezone()->getName(),
            IntlDateFormatter::TRADITIONAL,
            'd MMMM y',
        );

        $formatted = $formatter->format($date);

        return is_string($formatted) ? LocaleFormat::toLatinDigits($formatted) : '';
    }

    private function formatOptionalNumber(mixed $value, string $suffix = '%'): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $number = round((float) $value, 2);
        $text = abs($number - round($number)) < 0.001
            ? (string) (int) round($number)
            : number_format($number, 2, '.', '');

        return $text.$suffix;
    }
}
