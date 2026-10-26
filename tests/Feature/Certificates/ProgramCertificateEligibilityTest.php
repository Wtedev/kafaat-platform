<?php

namespace Tests\Feature\Certificates;

use App\Enums\AttendanceMarkSource;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SurveyType;
use App\Models\Certificate;
use App\Models\ProgramAttendanceLink;
use App\Models\ProgramAttendanceMark;
use App\Models\ProgramRegistration;
use App\Models\ProgramSurvey;
use App\Models\SurveyCompletion;
use App\Models\SurveyResponse;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Certificates\ProgramCertificateEligibility;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProgramCertificateEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_attended_days_out_of_three_are_not_eligible_when_all_three_are_required(): void
    {
        $program = $this->program();
        $days = [
            $this->link($program, 'اليوم الأول', now()->subDays(3)),
            $this->link($program, 'اليوم الثاني', now()->subDays(2)),
            $this->link($program, 'اليوم الثالث', now()->subDay()),
        ];
        $partial = $this->registration($program, RegistrationStatus::Approved);
        $complete = $this->registration($program, RegistrationStatus::Approved);
        $this->mark($days[0], $partial);
        $this->mark($days[1], $partial);
        foreach ($days as $day) {
            $this->mark($day, $complete);
        }

        $eligible = app(ProgramCertificateEligibility::class)->eligible(
            $program,
            array_map(fn (ProgramAttendanceLink $day): int => $day->id, $days),
        );

        $this->assertEqualsCanonicalizing([$complete->id], $eligible->pluck('id')->all());
    }

    public function test_an_anonymous_survey_counts_a_completion_and_ignores_a_named_response(): void
    {
        $program = $this->program();
        $survey = ProgramSurvey::query()->create([
            'training_program_id' => $program->id,
            'type' => SurveyType::Pre,
            'is_anonymous' => true,
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
        ]);
        $finished = $this->registration($program, RegistrationStatus::Approved);
        $responseOnly = $this->registration($program, RegistrationStatus::Approved);
        SurveyCompletion::query()->create([
            'program_survey_id' => $survey->id,
            'registration_id' => $finished->id,
        ]);
        SurveyResponse::query()->create([
            'program_survey_id' => $survey->id,
            'registration_id' => $responseOnly->id,
        ]);

        $eligible = app(ProgramCertificateEligibility::class)->eligible($program, [], [SurveyType::Pre]);

        $this->assertEqualsCanonicalizing([$finished->id], $eligible->pluck('id')->all());
    }

    public function test_a_registration_that_already_has_a_certificate_is_excluded(): void
    {
        $program = $this->program();
        $issued = $this->registration($program, RegistrationStatus::Completed);
        $waiting = $this->registration($program, RegistrationStatus::Approved);
        $this->certificate($issued);

        $eligible = app(ProgramCertificateEligibility::class)->eligible($program);

        $this->assertEqualsCanonicalizing([$waiting->id], $eligible->pluck('id')->all());
    }

    public function test_no_criteria_includes_every_accepted_registration_without_a_certificate(): void
    {
        $program = $this->program();
        $approved = $this->registration($program, RegistrationStatus::Approved);
        $completed = $this->registration($program, RegistrationStatus::Completed);
        $this->registration($program, RegistrationStatus::Pending);
        $this->registration($program, RegistrationStatus::Rejected);

        $eligible = app(ProgramCertificateEligibility::class)->eligible($program);

        $this->assertEqualsCanonicalizing(
            [$approved->id, $completed->id],
            $eligible->pluck('id')->all(),
        );
        $this->assertSame(2, app(ProgramCertificateEligibility::class)->count($program));
    }

    public function test_a_beneficiary_cannot_hold_two_certificates_for_the_same_program(): void
    {
        $this->assertTrue(Schema::hasColumn('certificates', 'emailed_at'));

        $program = $this->program();
        $registration = $this->registration($program, RegistrationStatus::Approved);
        $certificate = $this->certificate($registration);
        $certificate->update(['emailed_at' => now()]);

        $this->assertNotNull($certificate->fresh()->emailed_at);

        $this->expectException(QueryException::class);
        $this->certificate($registration);
    }

    private function program(): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => 'برنامج الشهادات',
            'slug' => 'certificates-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ]);
    }

    private function registration(TrainingProgram $program, RegistrationStatus $status): ProgramRegistration
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
        ]);

        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => $status,
        ]);
    }

    private function link(TrainingProgram $program, string $name, Carbon $opensAt): ProgramAttendanceLink
    {
        return ProgramAttendanceLink::query()->create([
            'training_program_id' => $program->id,
            'name' => $name,
            'opens_at' => $opensAt,
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

    private function certificate(ProgramRegistration $registration): Certificate
    {
        return Certificate::query()->create([
            'user_id' => $registration->user_id,
            'certificateable_type' => TrainingProgram::class,
            'certificateable_id' => $registration->training_program_id,
            'certificate_number' => 'CERT-'.uniqid(),
            'verification_code' => bin2hex(random_bytes(16)),
            'issued_at' => now(),
        ]);
    }
}
