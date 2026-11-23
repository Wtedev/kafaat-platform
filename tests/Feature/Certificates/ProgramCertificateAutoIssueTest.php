<?php

namespace Tests\Feature\Certificates;

use App\Enums\AttendanceMarkSource;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SurveyType;
use App\Jobs\CheckProgramCertificateAutoIssueJob;
use App\Jobs\IssueProgramCertificateJob;
use App\Models\CertificateTemplate;
use App\Models\ProgramAttendanceLink;
use App\Models\ProgramAttendanceMark;
use App\Models\ProgramRegistration;
use App\Models\ProgramSurvey;
use App\Models\SurveyCompletion;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Certificates\ProgramCertificateAutoIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProgramCertificateAutoIssueTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_last_missing_criterion_queues_the_certificate(): void
    {
        Storage::fake('public');
        Queue::fake();

        [$program, $day1, $day2, $satisfaction] = $this->programWithCriteria(enabled: true);
        $registration = $this->registration($program);

        $this->mark($day1, $registration);
        $this->mark($day2, $registration);
        $this->assertFalse(app(ProgramCertificateAutoIssuer::class)->checkRegistration($registration->id));

        SurveyCompletion::query()->create([
            'program_survey_id' => $satisfaction->id,
            'registration_id' => $registration->id,
        ]);

        Queue::assertPushed(CheckProgramCertificateAutoIssueJob::class, fn ($job): bool => $job->registrationId === $registration->id);
        $this->assertTrue(app(ProgramCertificateAutoIssuer::class)->checkRegistration($registration->id));
        Queue::assertPushed(IssueProgramCertificateJob::class, fn ($job): bool => $job->registrationId === $registration->id);
    }

    public function test_one_attended_day_out_of_two_is_not_issued(): void
    {
        Storage::fake('public');
        Queue::fake();

        [$program, $day1, , $satisfaction] = $this->programWithCriteria(enabled: true);
        $registration = $this->registration($program);
        $this->mark($day1, $registration);
        SurveyCompletion::query()->create([
            'program_survey_id' => $satisfaction->id,
            'registration_id' => $registration->id,
        ]);

        $this->assertFalse(app(ProgramCertificateAutoIssuer::class)->checkRegistration($registration->id));
        Queue::assertNotPushed(IssueProgramCertificateJob::class);
    }

    public function test_programs_with_the_switch_off_are_left_alone(): void
    {
        Storage::fake('public');
        Queue::fake();

        [$program, $day1, $day2] = $this->programWithCriteria(enabled: false);
        $registration = $this->registration($program);
        $this->mark($day1, $registration);
        $this->mark($day2, $registration);

        Queue::assertNotPushed(CheckProgramCertificateAutoIssueJob::class);
        $this->assertSame(0, app(ProgramCertificateAutoIssuer::class)->sweep($program));
    }

    public function test_an_enabled_switch_without_criteria_never_issues_to_everyone(): void
    {
        Storage::fake('public');
        Queue::fake();

        [$program] = $this->programWithCriteria(enabled: true);
        $program->forceFill(['certificate_auto_criteria' => ['attendance_link_ids' => [], 'survey_types' => []]])->save();
        $this->registration($program);

        $this->assertSame(0, app(ProgramCertificateAutoIssuer::class)->sweep($program->refresh()));
        Queue::assertNotPushed(IssueProgramCertificateJob::class);
    }

    public function test_the_hourly_sweep_queues_everyone_already_eligible(): void
    {
        Storage::fake('public');
        Queue::fake();

        [$program, $day1, $day2, $satisfaction] = $this->programWithCriteria(enabled: true);
        $ready = $this->registration($program);
        $notReady = $this->registration($program);
        foreach ([$day1, $day2] as $day) {
            $this->mark($day, $ready);
        }
        $this->mark($day1, $notReady);
        SurveyCompletion::query()->create(['program_survey_id' => $satisfaction->id, 'registration_id' => $ready->id]);

        $this->artisan('certificates:auto-issue')->assertSuccessful();

        Queue::assertPushed(IssueProgramCertificateJob::class, 1);
        Queue::assertPushed(IssueProgramCertificateJob::class, fn ($job): bool => $job->registrationId === $ready->id);
    }

    /**
     * @return array{0: TrainingProgram, 1: ProgramAttendanceLink, 2: ProgramAttendanceLink, 3: ProgramSurvey}
     */
    private function programWithCriteria(bool $enabled): array
    {
        $staff = User::factory()->create(['role_type' => 'staff', 'is_active' => true]);
        $program = TrainingProgram::query()->create([
            'title' => 'ملتقى',
            'slug' => 'auto-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);
        $day1 = ProgramAttendanceLink::query()->create(['training_program_id' => $program->id, 'name' => 'اليوم الأول', 'opens_at' => now()->subDays(2)]);
        $day2 = ProgramAttendanceLink::query()->create(['training_program_id' => $program->id, 'name' => 'اليوم الثاني', 'opens_at' => now()->subDay()]);
        $satisfaction = ProgramSurvey::query()->create([
            'training_program_id' => $program->id,
            'type' => SurveyType::Satisfaction,
            'is_anonymous' => true,
        ]);

        $path = 'certificate-templates/'.$program->id.'/background.png';
        Storage::disk('public')->put($path, 'png');
        CertificateTemplate::query()->create(['training_program_id' => $program->id, 'background_path' => $path, 'elements' => []]);

        $program->forceFill([
            'certificate_auto_issue' => $enabled,
            'certificate_auto_criteria' => [
                'attendance_link_ids' => [$day1->id, $day2->id],
                'survey_types' => [SurveyType::Satisfaction->value],
            ],
            'certificate_auto_enabled_by' => $staff->id,
        ])->save();

        return [$program->refresh(), $day1, $day2, $satisfaction];
    }

    private function registration(TrainingProgram $program): ProgramRegistration
    {
        $user = User::factory()->create(['role_type' => 'beneficiary', 'is_active' => true]);

        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Approved,
        ]);
    }

    private function mark(ProgramAttendanceLink $link, ProgramRegistration $registration): void
    {
        ProgramAttendanceMark::query()->create([
            'program_attendance_link_id' => $link->id,
            'program_registration_id' => $registration->id,
            'attended_at' => now(),
            'source' => AttendanceMarkSource::Manual,
        ]);
    }
}
