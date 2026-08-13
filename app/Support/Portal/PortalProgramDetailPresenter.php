<?php

namespace App\Support\Portal;

use App\Enums\AttendanceStatus;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramPrepDayType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Models\Certificate;
use App\Models\ProgramAttendance;
use App\Models\ProgramPrepDay;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\AttendanceLiveSessionService;
use App\Services\ProgramAttendanceService;
use App\Support\ProgramRegistrationSuccessPresenter;
use App\Support\RegistrationEligibilitySupport;
use App\Support\TrainingProgramExtrasSupport;
use Illuminate\Support\Carbon;

/**
 * Assembles the beneficiary program-detail page from existing services.
 *
 * Does not recompute attendance % or certificate eligibility. Day labels
 * (حاضر / غائب / لم يُفتح التحضير بعد) are presentation only: future and
 * still-open days are never shown as absent, while
 * {@see ProgramAttendanceService::calculatePercentage()} is unchanged.
 */
final class PortalProgramDetailPresenter
{
    public function __construct(
        private readonly ProgramAttendanceService $attendance,
        private readonly AttendanceLiveSessionService $liveSessions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(ProgramRegistration $registration, User $viewer, ?Carbon $asOf = null): array
    {
        $registration->loadMissing([
            'trainingProgram.prepDays',
            'attendanceRecords',
        ]);

        $program = $registration->trainingProgram;
        $asOf = ($asOf ?? Carbon::now(config('app.timezone')))->timezone(config('app.timezone'));
        $today = $asOf->copy()->startOfDay();
        $todayString = $today->toDateString();

        $accepted = in_array($registration->status, [
            RegistrationStatus::Approved,
            RegistrationStatus::Completed,
        ], true);

        $summary = $program !== null
            ? $this->attendance->getSummary($registration)
            : ['total' => 0, 'present' => 0, 'not_present' => 0];
        $percentage = $program !== null
            ? $this->attendance->calculatePercentage($registration)
            : null;
        $score = $registration->score !== null ? (float) $registration->score : null;

        $todayPrep = $program !== null ? $this->attendance->todayPrepDay($program, $asOf) : null;
        $todayType = $todayPrep?->delivery_type;
        $liveSession = $program !== null && $todayType === ProgramPrepDayType::Remote
            ? $this->liveSessions->activeSessionFor($program, $todayString)
            : null;
        $liveSessionActive = $liveSession !== null && $liveSession->isActive();

        $attendancePass = ($program !== null && $accepted)
            ? ProgramRegistrationSuccessPresenter::present($program, $registration, $viewer)
            : null;

        $recordsByDate = $registration->attendanceRecords
            ->keyBy(fn (ProgramAttendance $row): string => $row->training_date->toDateString());

        $prepDays = $program !== null
            ? $program->prepDays->sortBy(fn (ProgramPrepDay $day): string => $day->dateString())->values()
            : collect();

        $attendanceLog = [];
        $absentCount = 0;
        $upcomingCount = 0;

        foreach ($prepDays as $day) {
            $row = $this->presentPrepDay(
                $day,
                $recordsByDate->get($day->dateString()),
                $todayString,
                $liveSessionActive && $day->dateString() === $todayString,
            );
            if ($row['status_key'] === 'absent') {
                $absentCount++;
            }
            if ($row['status_key'] === 'upcoming') {
                $upcomingCount++;
            }
            $attendanceLog[] = $row;
        }

        usort($attendanceLog, fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        $timeline = array_values(array_filter(
            $attendanceLog,
            fn (array $row): bool => $row['date'] >= $todayString,
        ));
        usort($timeline, fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        $certificate = $program !== null
            ? Certificate::query()
                ->where('user_id', $viewer->id)
                ->where('certificateable_type', $program->getMorphClass())
                ->where('certificateable_id', $program->id)
                ->first()
            : null;

        $eligibility = $this->eligibility($registration, $percentage, $score);
        $lifecycle = $this->lifecycle($program, $today);
        $primaryAction = $this->primaryAction(
            $program?->id,
            $accepted,
            $todayType,
            $liveSessionActive,
            $attendancePass,
            $certificate,
        );

        return [
            'registration' => $registration,
            'program' => $program,
            'header' => [
                'title' => $program?->title ?? 'برنامج',
                'registration_status' => $this->registrationStatus($registration->status),
                'program_status' => $lifecycle,
                'start_label' => $program?->start_date ? ar_date($program->start_date) : 'غير متاح',
                'end_label' => $program?->end_date ? ar_date($program->end_date) : 'غير متاح',
                'has_clock_times' => false,
                'timing_label' => $program?->portalTimingLabel($today),
                'delivery' => $this->delivery($program),
                'location' => $this->location($program),
                'catalog_url' => ($program !== null && filled($program->slug))
                    ? route('public.programs.show', $program->slug)
                    : null,
            ],
            'primary_action' => $primaryAction,
            'summary' => [
                'present' => $summary['present'],
                'absent' => $absentCount,
                'upcoming' => $upcomingCount,
                'total' => $summary['total'],
                'percentage' => $percentage,
                'percentage_label' => $percentage !== null ? en_num($percentage, 1).'%' : 'غير متاح',
                'percentage_note' => $percentage !== null
                    ? 'تُحسب على كل أيام البرنامج، بما فيها الأيام القادمة.'
                    : 'لا توجد أيام تحضير مسجّلة.',
                'score' => $score,
                'score_label' => $score !== null ? en_num($score, 1).' من 100' : 'غير متاح',
                'eligibility' => $eligibility,
            ],
            'attendance_log' => $attendanceLog,
            'timeline' => $timeline,
            'show_grades' => $score !== null,
            'grades' => $score !== null ? [
                'score_label' => en_num($score, 1).' من 100',
                'pass_label' => $eligibility['state'] === 'eligible' ? 'مستوفٍ لشرط المتوسط' : 'لم يستوفِ شرط المتوسط بعد',
                'average_label' => $eligibility['average_label'],
                'items' => [],
            ] : null,
            'certificate' => [
                'state' => $eligibility['state'],
                'label' => $eligibility['label'],
                'class' => $eligibility['class'],
                'reason' => $eligibility['reason'],
                'conditions' => [
                    'التسجيل مقبول أو مكتمل.',
                    'توفر نسبة الحضور والدرجة.',
                    'متوسط الحضور والدرجة لا يقل عن 75%.',
                ],
                'download_url' => $certificate?->downloadUrl(),
                'issued' => $certificate !== null,
            ],
            'enrollment' => [
                'id' => $registration->id,
                'registered_at' => ar_date_time($registration->created_at),
                'approved_at' => $registration->approved_at ? ar_date_time($registration->approved_at) : null,
                'updated_at' => ar_date_time($registration->updated_at),
                'rejected_reason' => $registration->status === RegistrationStatus::Rejected
                    ? ($registration->rejected_reason ?: null)
                    : null,
                'whatsapp_url' => $accepted && $program !== null
                    ? TrainingProgramExtrasSupport::whatsappGroupUrlFor($program, $viewer)
                    : null,
            ],
            'today_prep_type' => $todayType,
            'live_session' => $liveSession,
            'live_session_active' => $liveSessionActive,
            'attendance_pass' => $attendancePass,
            'accepted' => $accepted,
        ];
    }

    /**
     * @return array{
     *     date: string,
     *     day_name: string,
     *     date_label: string,
     *     type_key: string,
     *     type_label: string,
     *     status_key: string,
     *     status_label: string,
     *     marked_at: string|null,
     *     method_key: string|null,
     *     method_label: string,
     *     join_available: bool,
     *     session_message: string
     * }
     */
    private function presentPrepDay(
        ProgramPrepDay $day,
        ?ProgramAttendance $record,
        string $today,
        bool $remoteOpenToday,
    ): array {
        $date = $day->dateString();
        $present = $record !== null && $record->status === AttendanceStatus::Present;
        $required = $day->requires_attendance !== false;
        $isFuture = $date > $today;
        $isToday = $date === $today;

        if (! $required) {
            $statusKey = 'not_required';
            $statusLabel = 'غير مطلوب';
        } elseif ($present) {
            $statusKey = 'present';
            $statusLabel = 'حاضر';
        } elseif ($isFuture || $isToday) {
            $statusKey = 'upcoming';
            $statusLabel = 'لم يُفتح التحضير بعد';
        } else {
            $statusKey = 'absent';
            $statusLabel = 'غائب';
        }

        $methodKey = $present ? $this->methodKey($record?->notes) : null;
        $joinAvailable = $isToday && $day->delivery_type === ProgramPrepDayType::Remote && $remoteOpenToday && ! $present;

        $sessionMessage = match (true) {
            $present => 'تم تسجيل الحضور.',
            $joinAvailable => 'الجلسة مفتوحة الآن.',
            $isToday && $day->delivery_type === ProgramPrepDayType::Remote => 'بانتظار فتح جلسة التحضير.',
            $isToday && $day->delivery_type === ProgramPrepDayType::InPerson => 'التحضير اليوم عبر مسؤول التحضير (QR أو يدوي).',
            $isFuture => 'لم يبدأ وقتها بعد.',
            default => 'انتهت فرصة التحضير.',
        };

        return [
            'date' => $date,
            'day_name' => $this->attendance->dayName(
                $day->prep_date->timezone(config('app.timezone'))->dayOfWeek,
            ),
            'date_label' => $day->displayLabel(),
            'type_key' => $day->delivery_type->value,
            'type_label' => $day->delivery_type->label(),
            'status_key' => $statusKey,
            'status_label' => $statusLabel,
            'marked_at' => $present && $record?->created_at
                ? ar_date_time($record->created_at->timezone(config('app.timezone')))
                : null,
            'method_key' => $methodKey,
            'method_label' => $this->methodLabel($methodKey),
            'join_available' => $joinAvailable,
            'session_message' => $sessionMessage,
        ];
    }

    /**
     * @return array{key: string, label: string, class: string}
     */
    private function registrationStatus(RegistrationStatus $status): array
    {
        return match ($status) {
            RegistrationStatus::Pending => [
                'key' => 'pending',
                'label' => 'قيد المراجعة',
                'class' => $status->badgeClass(),
            ],
            RegistrationStatus::Approved => [
                'key' => 'approved',
                'label' => 'مقبول',
                'class' => $status->badgeClass(),
            ],
            RegistrationStatus::Rejected => [
                'key' => 'rejected',
                'label' => 'مرفوض',
                'class' => $status->badgeClass(),
            ],
            RegistrationStatus::Cancelled => [
                'key' => 'cancelled',
                'label' => 'منسحب',
                'class' => $status->badgeClass(),
            ],
            RegistrationStatus::Completed => [
                'key' => 'completed',
                'label' => 'مكتمل',
                'class' => $status->badgeClass(),
            ],
        };
    }

    /**
     * @return array{key: string, label: string, class: string}
     */
    private function lifecycle(?TrainingProgram $program, Carbon $today): array
    {
        if ($program === null) {
            return ['key' => 'unknown', 'label' => 'غير محدد', 'class' => config('brand.classes.badge_primary')];
        }

        if ($program->status === ProgramStatus::Archived) {
            return ['key' => 'closed', 'label' => 'مغلق', 'class' => config('brand.classes.badge_primary')];
        }

        $start = $program->start_date?->copy()->startOfDay();
        $end = $program->end_date?->copy()->startOfDay();

        if ($start === null && $end === null) {
            return ['key' => 'unknown', 'label' => 'غير محدد', 'class' => config('brand.classes.badge_primary')];
        }

        if ($start !== null && $today->lt($start)) {
            return ['key' => 'not_started', 'label' => 'لم يبدأ', 'class' => config('brand.classes.badge_accent')];
        }

        if ($end !== null && $today->gt($end)) {
            return ['key' => 'completed', 'label' => 'مكتمل', 'class' => config('brand.classes.badge_primary')];
        }

        return ['key' => 'running', 'label' => 'جارٍ', 'class' => config('brand.classes.badge_secondary')];
    }

    /**
     * @return array{key: string, label: string}
     */
    private function delivery(?TrainingProgram $program): array
    {
        return match ($program?->delivery_mode) {
            ProgramDeliveryMode::InPerson => ['key' => 'in_person', 'label' => 'حضوري'],
            ProgramDeliveryMode::Remote => ['key' => 'remote', 'label' => 'عن بُعد'],
            ProgramDeliveryMode::Hybrid => ['key' => 'hybrid', 'label' => 'مدمج'],
            default => ['key' => 'unknown', 'label' => 'غير متاح'],
        };
    }

    /**
     * @return array{kind: string, label: string}
     */
    private function location(?TrainingProgram $program): array
    {
        $mode = $program?->delivery_mode;

        if ($mode === ProgramDeliveryMode::Remote) {
            return [
                'kind' => 'remote',
                'label' => 'عن بُعد — يتم التحضير عبر جلسة تفتحها الإدارة.',
            ];
        }

        if ($mode?->hasPhysicalComponent() && filled($program?->venue)) {
            return ['kind' => 'venue', 'label' => (string) $program->venue];
        }

        if ($mode?->hasPhysicalComponent()) {
            return ['kind' => 'venue', 'label' => 'الموقع غير محدد'];
        }

        return ['kind' => 'none', 'label' => 'غير متاح'];
    }

    /**
     * @param  array<string, mixed>|null  $attendancePass
     * @return array{key: string, label: string, modal_id: string|null, href: string|null}|null
     */
    private function primaryAction(
        ?int $programId,
        bool $accepted,
        ?ProgramPrepDayType $todayType,
        bool $liveSessionActive,
        ?array $attendancePass,
        ?Certificate $certificate,
    ): ?array {
        if (! $accepted || $programId === null) {
            return null;
        }

        if ($todayType === ProgramPrepDayType::Remote && $liveSessionActive) {
            return [
                'key' => 'join_session',
                'label' => 'الانضمام للجلسة',
                'modal_id' => 'program-attendance-remote-'.$programId,
                'href' => null,
            ];
        }

        if ($todayType === ProgramPrepDayType::InPerson && ! empty($attendancePass['qr_data_uri'])) {
            return [
                'key' => 'show_qr',
                'label' => 'عرض رمز الحضور',
                'modal_id' => 'program-attendance-qr-'.$programId,
                'href' => null,
            ];
        }

        $download = $certificate?->downloadUrl();
        if ($download !== null) {
            return [
                'key' => 'download_certificate',
                'label' => 'تحميل الشهادة',
                'modal_id' => null,
                'href' => $download,
            ];
        }

        return null;
    }

    /**
     * @return array{state: string, label: string, reason: string, average_label: string|null, class: string}
     */
    private function eligibility(ProgramRegistration $registration, ?float $percentage, ?float $score): array
    {
        if (! in_array($registration->status, [
            RegistrationStatus::Approved,
            RegistrationStatus::Completed,
        ], true)) {
            return [
                'state' => 'undecided',
                'label' => 'لم تُحسم الأهلية بعد',
                'reason' => 'تُحسم أهلية الشهادة بعد قبول التسجيل.',
                'average_label' => null,
                'class' => config('brand.classes.badge_accent'),
            ];
        }

        $label = RegistrationEligibilitySupport::eligibilityLabel($percentage, $score);
        $average = RegistrationEligibilitySupport::averageScore($percentage, $score);
        $averageLabel = $average !== null ? en_num($average, 1).'%' : null;

        if ($label === 'بانتظار البيانات') {
            $missing = [];
            if ($percentage === null) {
                $missing[] = 'نسبة الحضور';
            }
            if ($score === null) {
                $missing[] = 'الدرجة';
            }

            return [
                'state' => 'undecided',
                'label' => 'لم تُحسم الأهلية بعد',
                'reason' => 'بانتظار '.implode(' و', $missing).'. الشرط: متوسط الحضور والدرجة لا يقل عن 75%.',
                'average_label' => $averageLabel,
                'class' => config('brand.classes.badge_accent'),
            ];
        }

        if ($label === 'مؤهل') {
            return [
                'state' => 'eligible',
                'label' => 'مؤهل',
                'reason' => 'متوسط الحضور والدرجة '.$averageLabel.' (الحد الأدنى 75%).',
                'average_label' => $averageLabel,
                'class' => config('brand.classes.badge_secondary'),
            ];
        }

        return [
            'state' => 'ineligible',
            'label' => 'غير مؤهل',
            'reason' => 'متوسط الحضور ('.en_num((float) $percentage, 1).'%) والدرجة ('.en_num((float) $score, 1).' من 100) = '.$averageLabel.' وهو أقل من 75%.',
            'average_label' => $averageLabel,
            'class' => config('brand.classes.badge_danger'),
        ];
    }

    private function methodKey(?string $notes): string
    {
        $notes = (string) $notes;

        if (str_contains($notes, 'QR') || str_contains($notes, 'بوابة QR')) {
            return 'qr';
        }

        if (str_contains($notes, 'تسجيل حضور ذاتي')) {
            return 'remote';
        }

        return 'manual';
    }

    private function methodLabel(?string $key): string
    {
        return match ($key) {
            'qr' => 'QR',
            'remote' => 'جلسة عن بُعد',
            'manual' => 'يدوي',
            default => '—',
        };
    }
}
