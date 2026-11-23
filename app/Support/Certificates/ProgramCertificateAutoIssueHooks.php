<?php

namespace App\Support\Certificates;

use App\Jobs\CheckProgramCertificateAutoIssueJob;
use App\Models\ProgramAttendanceMark;
use App\Models\ProgramRegistration;
use App\Models\SurveyCompletion;
use App\Models\SurveyResponse;
use App\Models\TrainingProgram;

/**
 * Re-checks a registration for an automatic certificate whenever it gains
 * something the criteria can ask for: an attendance mark or a survey answer.
 * Only programs with the switch on are queued.
 */
final class ProgramCertificateAutoIssueHooks
{
    public static function register(): void
    {
        ProgramAttendanceMark::created(static function (ProgramAttendanceMark $mark): void {
            self::queue((int) $mark->program_registration_id);
        });

        SurveyCompletion::created(static function (SurveyCompletion $completion): void {
            self::queue((int) $completion->registration_id);
        });

        SurveyResponse::created(static function (SurveyResponse $response): void {
            if ($response->registration_id !== null) {
                self::queue((int) $response->registration_id);
            }
        });
    }

    private static function queue(int $registrationId): void
    {
        if ($registrationId <= 0) {
            return;
        }

        $programId = ProgramRegistration::query()->whereKey($registrationId)->value('training_program_id');
        if ($programId === null) {
            return;
        }

        $enabled = TrainingProgram::query()
            ->whereKey($programId)
            ->where('certificate_auto_issue', true)
            ->exists();

        if (! $enabled) {
            return;
        }

        $job = new CheckProgramCertificateAutoIssueJob($registrationId);

        // Same queue as the issuing job: with a local public disk the web
        // service runs the `certificates` queue itself.
        if (config('filesystems.disks.public.driver') === 'local') {
            $job->onQueue('certificates');
        }

        dispatch($job);
    }
}
