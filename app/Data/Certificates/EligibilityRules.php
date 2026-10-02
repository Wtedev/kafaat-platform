<?php

namespace App\Data\Certificates;

use App\Enums\CertificateEligibilityMode;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class EligibilityRules
{
    public function __construct(
        public CertificateEligibilityMode $mode,
        public ?float $minAttendance,
        public ?float $minScore,
        public ?float $minAverage,
        public bool $requireCompletedStatus = true,
        public bool $requireActivityEnded = false,
        public ?float $minApprovedHours = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public static function fromArray(array $data): self
    {
        $validated = self::validate($data);

        return new self(
            mode: CertificateEligibilityMode::from($validated['mode']),
            minAttendance: isset($validated['min_attendance']) ? round((float) $validated['min_attendance'], 2) : null,
            minScore: isset($validated['min_score']) ? round((float) $validated['min_score'], 2) : null,
            minAverage: isset($validated['min_average']) ? round((float) $validated['min_average'], 2) : null,
            requireCompletedStatus: (bool) ($validated['require_completed_status'] ?? true),
            requireActivityEnded: (bool) ($validated['require_activity_ended'] ?? false),
            minApprovedHours: isset($validated['min_approved_hours']) ? round((float) $validated['min_approved_hours'], 2) : null,
        );
    }

    /**
     * المسار يكتمل عندما تكتمل كل دوراته المنشورة.
     */
    public static function legacyPathCourses(): self
    {
        return new self(
            mode: CertificateEligibilityMode::CompletedAllCourses,
            minAttendance: null,
            minScore: null,
            minAverage: null,
        );
    }

    public static function legacyVolunteerHours(float $hoursExpected): self
    {
        return new self(
            mode: CertificateEligibilityMode::MinApprovedHours,
            minAttendance: null,
            minScore: null,
            minAverage: null,
            minApprovedHours: round(max(0, $hoursExpected), 2),
        );
    }

    /**
     * شروط البرامج الحالية: متوسط الحضور والدرجة لا يقل عن 75.
     */
    public static function legacyProgramAverage(): self
    {
        return new self(
            mode: CertificateEligibilityMode::Average,
            minAttendance: null,
            minScore: null,
            minAverage: 75.0,
            requireCompletedStatus: true,
            requireActivityEnded: false,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(array $data): array
    {
        $mode = $data['mode'] ?? null;

        $validator = Validator::make($data, [
            'mode' => ['required', Rule::enum(CertificateEligibilityMode::class)],
            'min_attendance' => ['nullable', 'numeric', 'between:0,100'],
            'min_score' => ['nullable', 'numeric', 'between:0,100'],
            'min_average' => ['nullable', 'numeric', 'between:0,100'],
            'min_approved_hours' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'require_completed_status' => ['sometimes', 'boolean'],
            'require_activity_ended' => ['sometimes', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($data, $mode): void {
            $attendance = self::filled($data['min_attendance'] ?? null);
            $score = self::filled($data['min_score'] ?? null);
            $average = self::filled($data['min_average'] ?? null);
            $hours = self::filled($data['min_approved_hours'] ?? null);

            $require = function (bool $ok, string $field, string $message) use ($validator): void {
                if (! $ok) {
                    $validator->errors()->add($field, $message);
                }
            };

            $forbid = function (bool $present, string $field, string $message) use ($validator): void {
                if ($present) {
                    $validator->errors()->add($field, $message);
                }
            };

            match ($mode) {
                CertificateEligibilityMode::AttendanceOnly->value => (function () use ($require, $forbid, $attendance, $score, $average, $hours): void {
                    $require($attendance, 'min_attendance', 'نمط الحضور فقط يتطلب حد الحضور.');
                    $forbid($score, 'min_score', 'نمط الحضور فقط لا يقبل حد الدرجة.');
                    $forbid($average, 'min_average', 'نمط الحضور فقط لا يقبل حد المتوسط.');
                    $forbid($hours, 'min_approved_hours', 'نمط الحضور فقط لا يقبل حد الساعات.');
                })(),
                CertificateEligibilityMode::ScoreOnly->value => (function () use ($require, $forbid, $attendance, $score, $average, $hours): void {
                    $require($score, 'min_score', 'نمط الدرجة فقط يتطلب حد الدرجة.');
                    $forbid($attendance, 'min_attendance', 'نمط الدرجة فقط لا يقبل حد الحضور.');
                    $forbid($average, 'min_average', 'نمط الدرجة فقط لا يقبل حد المتوسط.');
                    $forbid($hours, 'min_approved_hours', 'نمط الدرجة فقط لا يقبل حد الساعات.');
                })(),
                CertificateEligibilityMode::Both->value => (function () use ($require, $forbid, $attendance, $score, $average, $hours): void {
                    $require($attendance, 'min_attendance', 'نمط الحضور والدرجة يتطلب حد الحضور.');
                    $require($score, 'min_score', 'نمط الحضور والدرجة يتطلب حد الدرجة.');
                    $forbid($average, 'min_average', 'نمط الحضور والدرجة لا يقبل حد المتوسط.');
                    $forbid($hours, 'min_approved_hours', 'نمط الحضور والدرجة لا يقبل حد الساعات.');
                })(),
                CertificateEligibilityMode::Average->value => (function () use ($require, $forbid, $attendance, $score, $average, $hours): void {
                    $require($average, 'min_average', 'نمط المتوسط يتطلب حد المتوسط.');
                    $forbid($attendance, 'min_attendance', 'نمط المتوسط لا يقبل حد الحضور المنفصل.');
                    $forbid($score, 'min_score', 'نمط المتوسط لا يقبل حد الدرجة المنفصل.');
                    $forbid($hours, 'min_approved_hours', 'نمط المتوسط لا يقبل حد الساعات.');
                })(),
                CertificateEligibilityMode::CompletedAllCourses->value => (function () use ($forbid, $attendance, $score, $average, $hours): void {
                    $forbid($attendance, 'min_attendance', 'نمط إكمال الدورات لا يقبل حد الحضور.');
                    $forbid($score, 'min_score', 'نمط إكمال الدورات لا يقبل حد الدرجة.');
                    $forbid($average, 'min_average', 'نمط إكمال الدورات لا يقبل حد المتوسط.');
                    $forbid($hours, 'min_approved_hours', 'نمط إكمال الدورات لا يقبل حد الساعات.');
                })(),
                CertificateEligibilityMode::MinApprovedHours->value => (function () use ($require, $forbid, $attendance, $score, $average, $hours): void {
                    $require($hours, 'min_approved_hours', 'نمط الساعات المعتمدة يتطلب حد الساعات.');
                    $forbid($attendance, 'min_attendance', 'نمط الساعات المعتمدة لا يقبل حد الحضور.');
                    $forbid($score, 'min_score', 'نمط الساعات المعتمدة لا يقبل حد الدرجة.');
                    $forbid($average, 'min_average', 'نمط الساعات المعتمدة لا يقبل حد المتوسط.');
                })(),
                default => null,
            };
        });

        return $validator->validate();
    }

    public function summarySentence(): string
    {
        $sentence = match ($this->mode) {
            CertificateEligibilityMode::AttendanceOnly => 'حضر '.$this->summaryNumber($this->minAttendance).'% فأكثر',
            CertificateEligibilityMode::ScoreOnly => 'حصل على '.$this->summaryNumber($this->minScore).' فأكثر في الاختبار',
            CertificateEligibilityMode::Both => 'حضر '.$this->summaryNumber($this->minAttendance).'% فأكثر وحصل على '.$this->summaryNumber($this->minScore).' فأكثر في الاختبار',
            CertificateEligibilityMode::Average => 'بلغ متوسط حضوره ودرجته '.$this->summaryNumber($this->minAverage).'% فأكثر',
            CertificateEligibilityMode::CompletedAllCourses => 'أكمل كل دورات المسار',
            CertificateEligibilityMode::MinApprovedHours => 'بلغت ساعاته التطوعية المعتمدة '.$this->summaryNumber($this->minApprovedHours).' فأكثر',
        };

        if ($this->requireCompletedStatus) {
            $sentence .= '، بشرط أن يكون التسجيل مقبولاً أو مكتملاً';
        }

        if ($this->requireActivityEnded) {
            $sentence .= '، وبعد انتهاء النشاط';
        }

        return $sentence;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode->value,
            'min_attendance' => $this->minAttendance,
            'min_score' => $this->minScore,
            'min_average' => $this->minAverage,
            'min_approved_hours' => $this->minApprovedHours,
            'require_completed_status' => $this->requireCompletedStatus,
            'require_activity_ended' => $this->requireActivityEnded,
        ];
    }

    private function summaryNumber(?float $value): string
    {
        $rounded = round((float) $value, 2);

        if (abs($rounded - round($rounded)) < 0.001) {
            return (string) (int) round($rounded);
        }

        return number_format($rounded, 2, '.', '');
    }

    private static function filled(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }
}
