<?php

namespace App\Services\ProgramActivity;

use App\Enums\AttendanceStatus;
use App\Enums\ProgramActivityAction;
use App\Enums\RegistrationStatus;
use App\Models\Certificate;
use App\Models\ProgramAttendance;
use App\Models\ProgramAttendanceMark;
use App\Models\ProgramBroadcast;
use App\Models\ProgramRegistration;
use App\Models\SurveyCompletion;
use App\Models\TrainingProgram;
use App\Models\User;

/**
 * Model events that feed the program activity log, so every path that changes a
 * registration, attendance day, survey, certificate or broadcast is recorded
 * with its time, whichever screen or job triggered it.
 */
final class ProgramActivityRecorder
{
    public static function register(): void
    {
        ProgramRegistration::created(static function (ProgramRegistration $registration): void {
            self::log()->record(
                (int) $registration->training_program_id,
                ProgramActivityAction::RegistrationCreated,
                $registration,
                self::userName($registration->user_id),
            );
        });

        ProgramRegistration::updated(static function (ProgramRegistration $registration): void {
            if (! $registration->wasChanged('status')) {
                return;
            }

            $action = match ($registration->status) {
                RegistrationStatus::Approved => ProgramActivityAction::RegistrationApproved,
                RegistrationStatus::Rejected => ProgramActivityAction::RegistrationRejected,
                RegistrationStatus::Cancelled => ProgramActivityAction::RegistrationCancelled,
                RegistrationStatus::Pending => ProgramActivityAction::RegistrationReopened,
                default => null,
            };

            if ($action === null) {
                return;
            }

            $properties = [];
            if ($action === ProgramActivityAction::RegistrationRejected && filled($registration->rejected_reason)) {
                $properties['reason'] = (string) $registration->rejected_reason;
            }

            $actor = $action === ProgramActivityAction::RegistrationApproved && $registration->approved_by !== null
                ? User::query()->find($registration->approved_by)
                : null;

            self::log()->record(
                (int) $registration->training_program_id,
                $action,
                $registration,
                self::userName($registration->user_id),
                $properties,
                $actor,
            );
        });

        ProgramAttendance::saved(static function (ProgramAttendance $attendance): void {
            if (! $attendance->wasRecentlyCreated && ! $attendance->wasChanged('status')) {
                return;
            }

            $registration = $attendance->registration()->first(['id', 'training_program_id', 'user_id']);
            if ($registration === null) {
                return;
            }

            $status = $attendance->status instanceof AttendanceStatus ? $attendance->status : AttendanceStatus::tryFrom((string) $attendance->status);
            $date = $attendance->training_date?->toDateString();

            self::log()->record(
                (int) $registration->training_program_id,
                ProgramActivityAction::AttendanceMarked,
                $registration,
                self::userName($registration->user_id),
                array_filter([
                    'date' => $date,
                    'status' => $status?->value,
                    'notes' => filled($attendance->notes) ? (string) $attendance->notes : null,
                ]),
            );
        });

        ProgramAttendance::deleted(static function (ProgramAttendance $attendance): void {
            $registration = $attendance->registration()->first(['id', 'training_program_id', 'user_id']);
            if ($registration === null) {
                return;
            }

            $date = $attendance->training_date?->toDateString();

            self::log()->record(
                (int) $registration->training_program_id,
                ProgramActivityAction::AttendanceCleared,
                $registration,
                self::userName($registration->user_id),
                array_filter(['date' => $date]),
            );
        });

        ProgramAttendanceMark::created(static function (ProgramAttendanceMark $mark): void {
            $link = $mark->link()->first(['id', 'training_program_id', 'name']);
            $registration = $mark->registration()->first(['id', 'user_id']);
            if ($link === null) {
                return;
            }

            self::log()->record(
                (int) $link->training_program_id,
                ProgramActivityAction::AttendanceMarked,
                $registration,
                $registration ? self::userName($registration->user_id) : null,
                array_filter([
                    'link' => (string) $link->name,
                    'source' => $mark->source instanceof \BackedEnum ? $mark->source->value : (string) $mark->source,
                ]),
            );
        });

        SurveyCompletion::created(static function (SurveyCompletion $completion): void {
            $survey = $completion->survey()->first(['id', 'training_program_id', 'type']);
            if ($survey === null) {
                return;
            }

            $registration = $completion->registration()->first(['id', 'user_id']);

            self::log()->record(
                (int) $survey->training_program_id,
                ProgramActivityAction::SurveyCompleted,
                $registration,
                trim(($registration ? self::userName($registration->user_id) : '').' — '.$survey->type?->label(), ' —'),
                ['survey' => $survey->type?->value],
            );
        });

        Certificate::created(static function (Certificate $certificate): void {
            if ($certificate->certificateable_type !== (new TrainingProgram)->getMorphClass()) {
                return;
            }

            self::log()->record(
                (int) $certificate->certificateable_id,
                ProgramActivityAction::CertificateIssued,
                $certificate,
                self::userName($certificate->user_id),
                ['number' => $certificate->certificate_number],
            );
        });

        ProgramBroadcast::created(static function (ProgramBroadcast $broadcast): void {
            self::log()->record(
                (int) $broadcast->training_program_id,
                ProgramActivityAction::BroadcastCreated,
                $broadcast,
                (string) $broadcast->subject,
                actor: $broadcast->created_by !== null ? User::query()->find($broadcast->created_by) : null,
            );
        });
    }

    private static function log(): ProgramActivityLog
    {
        return app(ProgramActivityLog::class);
    }

    private static function userName(mixed $userId): string
    {
        if ($userId === null) {
            return '';
        }

        return (string) (User::query()->whereKey($userId)->value('name') ?? '');
    }
}
