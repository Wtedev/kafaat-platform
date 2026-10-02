<?php

namespace Tests\Feature\Certificates;

use App\Data\Certificates\EligibilityRules;
use App\Enums\CertificateEligibilityMode;
use App\Enums\CertificateEligibilityStatus;
use App\Enums\CertificatePdfStatus;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Policies\CertificateTemplatePolicy;
use App\Services\Certificates\CertificateEligibilityService;
use App\Services\Certificates\CertificateTemplateBackfill;
use App\Services\ProgramAttendanceService;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class CertificateEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    public function test_each_mode_respects_the_boundary_between_79_99_and_80(): void
    {
        $program = $this->program();
        $below = $this->registration($program, ['score' => 79.99]);
        $atLimit = $this->registration($program, ['score' => 80]);

        $this->template($program, EligibilityRules::fromArray([
            'mode' => CertificateEligibilityMode::ScoreOnly->value,
            'min_score' => 80,
        ]));

        $service = $this->serviceReturning([$below->id => null, $atLimit->id => null]);
        $results = $service->evaluateMany(collect([$below, $atLimit]));

        $this->assertFalse($results[$below->id]->eligible);
        $this->assertSame(CertificateEligibilityStatus::NotEligible, $results[$below->id]->status);
        $this->assertSame(['الدرجة 79.99 أقل من الحد 80'], $results[$below->id]->reasons);
        $this->assertTrue($results[$atLimit->id]->eligible);

        $program->certificateTemplate()->delete();
        $this->template($program, EligibilityRules::fromArray([
            'mode' => CertificateEligibilityMode::AttendanceOnly->value,
            'min_attendance' => 80,
        ]));
        $attendance = $this->serviceReturning([$below->id => 79.99, $atLimit->id => 80]);

        $this->assertSame(
            ['الحضور 79.99% أقل من الحد 80%'],
            $attendance->evaluate($below)->reasons,
        );
        $this->assertTrue($attendance->evaluate($atLimit)->eligible);

        $program->certificateTemplate()->delete();
        $this->template($program, EligibilityRules::fromArray([
            'mode' => CertificateEligibilityMode::Both->value,
            'min_attendance' => 80,
            'min_score' => 80,
        ]));
        $both = $this->serviceReturning([$below->id => 80, $atLimit->id => 80]);
        $below->score = 79.99;
        $atLimit->score = 80;

        $this->assertSame(CertificateEligibilityStatus::NotEligible, $both->evaluate($below)->status);
        $this->assertTrue($both->evaluate($atLimit)->eligible);

        $program->certificateTemplate()->delete();
        $this->template($program, EligibilityRules::fromArray([
            'mode' => CertificateEligibilityMode::Average->value,
            'min_average' => 80,
        ]));
        $average = $this->serviceReturning([$below->id => 79.99, $atLimit->id => 80]);
        $below->score = 79.99;
        $atLimit->score = 80;

        $this->assertSame(['المتوسط 79.99% أقل من الحد 80%'], $average->evaluate($below)->reasons);
        $this->assertTrue($average->evaluate($atLimit)->eligible);
    }

    public function test_blank_score_is_eligible_for_attendance_only(): void
    {
        $program = $this->program();
        $registration = $this->registration($program, ['score' => null]);
        $this->template($program, EligibilityRules::fromArray([
            'mode' => CertificateEligibilityMode::AttendanceOnly->value,
            'min_attendance' => 80,
        ]));

        $result = $this->serviceReturning([$registration->id => 80])->evaluate($registration);

        $this->assertTrue($result->eligible);
        $this->assertSame(CertificateEligibilityStatus::Eligible, $result->status);
    }

    public function test_blank_score_with_both_mode_is_awaiting_data(): void
    {
        $program = $this->program();
        $registration = $this->registration($program, ['score' => null]);
        $this->template($program, EligibilityRules::fromArray([
            'mode' => CertificateEligibilityMode::Both->value,
            'min_attendance' => 80,
            'min_score' => 60,
        ]));

        $result = $this->serviceReturning([$registration->id => 90])->evaluate($registration);

        $this->assertFalse($result->eligible);
        $this->assertSame(CertificateEligibilityStatus::AwaitingData, $result->status);
        $this->assertSame(['لم تُرصد الدرجة بعد'], $result->reasons);
    }

    public function test_program_without_a_template_is_not_configured(): void
    {
        $program = $this->program();
        $registration = $this->registration($program);

        $result = app(CertificateEligibilityService::class)->evaluate($registration);

        $this->assertFalse($result->eligible);
        $this->assertSame(CertificateEligibilityStatus::NotConfigured, $result->status);
    }

    public function test_backfill_keeps_the_current_average_75_behavior_and_links_issued_certificates(): void
    {
        $program = $this->program();
        $user = User::factory()->create();
        $eligible = $this->registration($program, [
            'user_id' => $user->id,
            'score' => 80,
            'status' => RegistrationStatus::Completed,
        ]);
        $missingScore = $this->registration($program, ['score' => null]);
        $certificate = Certificate::query()->create([
            'user_id' => $user->id,
            'certificateable_type' => $program->getMorphClass(),
            'certificateable_id' => $program->id,
            'certificate_number' => 'CERT-BACKFILL-1',
            'verification_code' => bin2hex(random_bytes(16)),
            'issued_at' => now(),
            'file_path' => 'certificates/CERT-BACKFILL-1.pdf',
        ]);

        app(CertificateTemplateBackfill::class)->backfillTrainingPrograms();

        $template = $program->certificateTemplate()->first();
        $this->assertNotNull($template);
        $this->assertSame('ready', $template->status->value);
        $this->assertNull($template->background_path);
        $this->assertSame(CertificateEligibilityMode::Average, $template->eligibility->mode);
        $this->assertSame(75.0, $template->eligibility->minAverage);
        $this->assertSame(1, $template->version);

        $certificate->refresh();
        $this->assertSame($template->id, $certificate->certificate_template_id);
        $this->assertSame(1, $certificate->template_version);
        $this->assertSame(CertificatePdfStatus::Pending, $certificate->pdf_status);

        $service = $this->serviceReturning([
            $eligible->id => 80,
            $missingScore->id => 80,
        ]);

        $this->assertTrue($service->evaluate($eligible)->eligible);
        $missing = $service->evaluate($missingScore);
        $this->assertFalse($missing->eligible);
        $this->assertSame(CertificateEligibilityStatus::AwaitingData, $missing->status);
    }

    public function test_data_forum_backfill_requires_full_attendance_and_auto_issue(): void
    {
        $forum = TrainingProgram::query()->create([
            'title' => 'ملتقى تحليل البيانات 2',
            'slug' => CertificateTemplateBackfill::DATA_FORUM_SLUG,
            'status' => ProgramStatus::Published,
            'published_at' => now(),
        ]);
        $other = $this->program();

        app(CertificateTemplateBackfill::class)->backfillTrainingPrograms();
        app(CertificateTemplateBackfill::class)->enableAutoIssueForTrainingPrograms();

        $forumTemplate = $forum->certificateTemplate()->first();
        $otherTemplate = $other->certificateTemplate()->first();

        $this->assertSame(CertificateEligibilityMode::AttendanceOnly, $forumTemplate->eligibility->mode);
        $this->assertSame(100.0, $forumTemplate->eligibility->minAttendance);
        $this->assertTrue($forumTemplate->auto_issue);
        $this->assertSame(CertificateEligibilityMode::Average, $otherTemplate->eligibility->mode);
        $this->assertSame(75.0, $otherTemplate->eligibility->minAverage);
        $this->assertTrue($otherTemplate->auto_issue);
    }

    public function test_evaluate_many_uses_a_constant_query_count(): void
    {
        $program = $this->program();
        $this->template($program, EligibilityRules::fromArray([
            'mode' => CertificateEligibilityMode::AttendanceOnly->value,
            'min_attendance' => 80,
            'require_completed_status' => false,
        ]));

        $one = collect([$this->registration($program)]);
        $many = collect(range(1, 6))->map(fn (): ProgramRegistration => $this->registration($program));

        $service = app(CertificateEligibilityService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $service->evaluateMany($one);
        $oneCount = count(DB::getQueryLog());

        DB::flushQueryLog();
        $service->evaluateMany($many);
        $manyCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($oneCount, $manyCount);
        $this->assertLessThanOrEqual(4, $manyCount);
    }

    public function test_template_changes_are_written_to_the_activity_log(): void
    {
        $program = $this->program();
        $template = $this->template($program, EligibilityRules::legacyProgramAverage());

        $template->update(['version' => 2]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $template->getMorphClass(),
            'subject_id' => $template->id,
            'event' => 'updated',
            'description' => 'عُدّل قالب الشهادة',
        ]);
    }

    public function test_admin_role_receives_template_permission_and_program_editors_can_manage_it(): void
    {
        $this->seedRbacRoles();

        $adminRole = Role::findByName(RbacCatalog::ROLE_ADMIN);
        $this->assertTrue($adminRole->hasPermissionTo('certificate_templates.manage'));

        $program = $this->program();
        $template = $this->template($program, EligibilityRules::legacyProgramAverage());
        $policy = app(CertificateTemplatePolicy::class);

        $admin = User::factory()->create(['role_type' => 'admin', 'is_active' => true]);
        $editor = User::factory()->create(['role_type' => 'staff', 'is_active' => true]);
        $editor->givePermissionTo('programs.update');
        $program->forceFill(['owner_id' => $editor->id])->save();

        $outsider = User::factory()->create(['role_type' => 'staff', 'is_active' => true]);
        $outsider->givePermissionTo(['programs.update', 'certificate_templates.manage']);

        $this->assertTrue($policy->manage($admin, $template));
        $this->assertTrue($policy->manage($editor, $template->fresh()));
        $this->assertFalse($policy->manage($outsider, $template));
    }

    private function program(): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => 'برنامج الشهادات',
            'slug' => 'cert-template-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function registration(TrainingProgram $program, array $overrides = []): ProgramRegistration
    {
        return ProgramRegistration::query()->create(array_merge([
            'training_program_id' => $program->id,
            'user_id' => User::factory()->create()->id,
            'status' => RegistrationStatus::Approved,
            'score' => 80,
        ], $overrides));
    }

    private function template(TrainingProgram $program, EligibilityRules $rules): CertificateTemplate
    {
        return CertificateTemplate::query()->create([
            'owner_type' => $program->getMorphClass(),
            'owner_id' => $program->id,
            'eligibility' => $rules,
            'status' => 'ready',
            'version' => 1,
        ]);
    }

    /**
     * @param  array<int, float|null>  $percentages
     */
    private function serviceReturning(array $percentages): CertificateEligibilityService
    {
        $attendance = $this->createMock(ProgramAttendanceService::class);
        $attendance->method('percentagesForRegistrations')->willReturnCallback(
            function ($registrations) use ($percentages): array {
                $resolved = [];
                foreach ($registrations as $registration) {
                    $resolved[$registration->getKey()] = $percentages[$registration->getKey()] ?? null;
                }

                return $resolved;
            },
        );

        return new CertificateEligibilityService($attendance);
    }
}
