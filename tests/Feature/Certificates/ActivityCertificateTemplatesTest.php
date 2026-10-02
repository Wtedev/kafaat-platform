<?php

namespace Tests\Feature\Certificates;

use App\Enums\CertificateEligibilityMode;
use App\Enums\CertificatePdfStatus;
use App\Enums\CertificateTemplateStatus;
use App\Enums\OpportunityStatus;
use App\Enums\PathStatus;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\VolunteerHoursStatus;
use App\Filament\Resources\LearningPathResource\Pages\ManagePathCertificateDesign;
use App\Filament\Resources\LearningPathResource\Pages\ViewLearningPath;
use App\Filament\Resources\LearningPathResource\RelationManagers\PathCertificatesRelationManager;
use App\Filament\Resources\VolunteerOpportunityResource\Pages\ManageVolunteerCertificateDesign;
use App\Filament\Resources\VolunteerOpportunityResource\Pages\ViewVolunteerOpportunity;
use App\Filament\Resources\VolunteerOpportunityResource\RelationManagers\VolunteerCertificatesRelationManager;
use App\Jobs\IssueEligibleCertificatesJob;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\EmailLog;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerHour;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
use App\Services\Certificates\CertificateIssuanceService;
use App\Services\Certificates\CertificateRenderer;
use App\Services\Certificates\CertificateTemplateBackfill;
use App\Services\CertificateService;
use App\Services\ProgressService;
use App\Services\VolunteerHoursService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class ActivityCertificateTemplatesTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('public');
        Storage::fake('local');
        $this->mock(CertificateRenderer::class, function ($mock): void {
            $mock->shouldReceive('render')->andReturn('%PDF-1.4');
        });
    }

    public function test_path_and_volunteer_tabs_are_visible_only_to_authorized_users(): void
    {
        $admin = $this->admin();
        $outsider = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $outsider->assignRole('staff');
        $path = $this->path();
        $opportunity = $this->opportunity(10);

        $this->actingAs($admin);
        $this->assertTrue(PathCertificatesRelationManager::canViewForRecord($path, ViewLearningPath::class));
        $this->assertTrue(VolunteerCertificatesRelationManager::canViewForRecord($opportunity, ViewVolunteerOpportunity::class));

        $this->actingAs($outsider);
        $this->assertFalse(PathCertificatesRelationManager::canViewForRecord($path, ViewLearningPath::class));
        $this->assertFalse(VolunteerCertificatesRelationManager::canViewForRecord($opportunity, ViewVolunteerOpportunity::class));
    }

    public function test_path_issue_waits_until_every_published_course_is_completed(): void
    {
        $admin = $this->admin();
        $path = $this->path();
        $this->readyTemplate($path, [
            'mode' => CertificateEligibilityMode::CompletedAllCourses->value,
            'require_completed_status' => true,
            'require_activity_ended' => false,
        ], true);
        [$first, $second] = [$this->publishedProgram($path), $this->publishedProgram($path)];
        $user = $this->beneficiary('مسار مكتمل');
        $this->completeProgram($first, $user);
        $registration = $this->pathRegistration($path, $user);

        app(ProgressService::class)->completePathIfEligible($user, $path);

        $this->assertSame(RegistrationStatus::Approved, $registration->fresh()->status);
        $this->assertSame(0, Certificate::query()->count());

        $this->completeProgram($second, $user);
        app(ProgressService::class)->completePathIfEligible($user, $path->fresh());

        $registration->refresh();
        $this->assertSame(RegistrationStatus::Completed, $registration->status);
        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_path_batch_issues_only_learners_who_finished_every_course(): void
    {
        $admin = $this->admin();
        $path = $this->path();
        $this->readyTemplate($path, [
            'mode' => CertificateEligibilityMode::CompletedAllCourses->value,
            'require_completed_status' => true,
            'require_activity_ended' => false,
        ], true);
        $program = $this->publishedProgram($path);
        $done = $this->beneficiary('أنهى المسار');
        $waiting = $this->beneficiary('لم ينهِ المسار');
        $this->completeProgram($program, $done);
        $this->pathRegistration($path, $done);
        $this->pathRegistration($path, $waiting);

        Bus::fake();
        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($admin)
            ->test(PathCertificatesRelationManager::class, [
                'ownerRecord' => $path,
                'pageClass' => ViewLearningPath::class,
            ])
            ->assertSee('الدورات المكتملة')
            ->callAction(TestAction::make('issueEligible')->table());

        Bus::assertBatched(function ($batch): bool {
            return collect($batch->jobs)->flatten()->filter(
                fn ($job): bool => $job instanceof IssueEligibleCertificatesJob,
            )->count() === 1;
        });
    }

    public function test_draft_template_never_auto_issues_a_path_certificate(): void
    {
        $path = $this->path();
        CertificateTemplate::query()->create([
            'owner_type' => $path->getMorphClass(),
            'owner_id' => $path->id,
            'status' => CertificateTemplateStatus::Draft,
            'auto_issue' => true,
            'eligibility' => [
                'mode' => CertificateEligibilityMode::CompletedAllCourses->value,
                'require_completed_status' => true,
                'require_activity_ended' => false,
            ],
            'elements' => [],
            'version' => 1,
        ]);
        $program = $this->publishedProgram($path);
        $user = $this->beneficiary('مسودة');
        $this->completeProgram($program, $user);
        $registration = $this->pathRegistration($path, $user);

        app(ProgressService::class)->completePathIfEligible($user, $path);

        $this->assertSame(RegistrationStatus::Completed, $registration->fresh()->status);
        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_volunteer_certificate_follows_approved_hours_and_auto_issue(): void
    {
        $admin = $this->admin();
        $opportunity = $this->opportunity(8);
        $this->readyTemplate($opportunity, [
            'mode' => CertificateEligibilityMode::MinApprovedHours->value,
            'min_approved_hours' => 8,
            'require_completed_status' => true,
            'require_activity_ended' => false,
        ], true);
        $user = $this->beneficiary('متطوع');
        $registration = VolunteerRegistration::query()->create([
            'user_id' => $user->id,
            'opportunity_id' => $opportunity->id,
            'status' => RegistrationStatus::Approved,
        ]);
        $entry = VolunteerHour::query()->create([
            'user_id' => $user->id,
            'opportunity_id' => $opportunity->id,
            'hours' => 8,
            'status' => VolunteerHoursStatus::Pending,
        ]);

        app(VolunteerHoursService::class)->approveHours($entry, $admin);

        $this->assertSame(RegistrationStatus::Completed, $registration->fresh()->status);
        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_volunteer_auto_issue_stays_off_until_the_template_allows_it(): void
    {
        $admin = $this->admin();
        $opportunity = $this->opportunity(5);
        $this->readyTemplate($opportunity, [
            'mode' => CertificateEligibilityMode::MinApprovedHours->value,
            'min_approved_hours' => 5,
            'require_completed_status' => true,
            'require_activity_ended' => false,
        ], false);
        $user = $this->beneficiary('بدون إصدار تلقائي');
        VolunteerRegistration::query()->create([
            'user_id' => $user->id,
            'opportunity_id' => $opportunity->id,
            'status' => RegistrationStatus::Approved,
        ]);
        $entry = VolunteerHour::query()->create([
            'user_id' => $user->id,
            'opportunity_id' => $opportunity->id,
            'hours' => 5,
            'status' => VolunteerHoursStatus::Pending,
        ]);

        app(VolunteerHoursService::class)->approveHours($entry, $admin);

        $this->assertSame(0, Certificate::query()->count());

        $this->withSession(['otp_verified' => true]);
        Livewire::actingAs($admin)
            ->test(VolunteerCertificatesRelationManager::class, [
                'ownerRecord' => $opportunity,
                'pageClass' => ViewVolunteerOpportunity::class,
            ])
            ->assertSee('الساعات المعتمدة')
            ->callAction(TestAction::make('issueEligible')->table());

        $this->assertSame(1, Certificate::query()->count());
    }

    public function test_exceptional_path_issue_and_revoke_follow_the_program_rules(): void
    {
        $admin = $this->admin();
        $path = $this->path();
        $this->readyTemplate($path, [
            'mode' => CertificateEligibilityMode::CompletedAllCourses->value,
            'require_completed_status' => true,
            'require_activity_ended' => false,
        ], true);
        $this->publishedProgram($path);
        $user = $this->beneficiary('استثنائي');
        $user->assignRole('beneficiary');
        $registration = $this->pathRegistration($path, $user);

        $certificate = app(CertificateIssuanceService::class)->issueExceptional($registration, $admin, 'موافقة الإدارة');
        $this->assertSame(CertificatePdfStatus::Generated, $certificate->fresh()->pdf_status);

        app(CertificateIssuanceService::class)->revoke($certificate, $admin, 'بيانات غير صحيحة');

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $certificate->id,
            'event' => 'certificate_revoked',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $certificate->id,
            'event' => 'exceptional_issue',
        ]);

        $this->get(route('certificates.verify', $certificate->verification_code))
            ->assertOk()
            ->assertSee('هذه الشهادة ملغاة');

        $this->actingAsOtpVerified($user)
            ->get(route('portal.certificates'))
            ->assertOk()
            ->assertDontSee($certificate->certificate_number);

        $this->actingAsOtpVerified($user)
            ->get(route('certificates.download', $certificate))
            ->assertNotFound();
    }

    public function test_volunteer_email_subject_uses_the_snapshot_title(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $opportunity = $this->opportunity(4);
        $user = $this->beneficiary('بريد التطوع');
        $relative = 'certificates/volunteer-'.uniqid().'.pdf';
        Storage::disk('public')->put($relative, '%PDF-1.4');
        $certificate = Certificate::query()->create([
            'user_id' => $user->id,
            'certificateable_type' => VolunteerOpportunity::class,
            'certificateable_id' => $opportunity->id,
            'certificate_number' => 'CERT-VOL-'.uniqid(),
            'verification_code' => bin2hex(random_bytes(8)),
            'file_path' => $relative,
            'pdf_status' => CertificatePdfStatus::Generated,
            'issued_at' => now(),
            'data_snapshot' => [
                'activity_title' => 'فرصة التشجير',
                'recipient_name' => $user->name,
            ],
        ]);

        $this->assertTrue(app(CertificateService::class)->emailCertificate($certificate, $admin));
        $this->assertSame(
            'شهادتك جاهزة — فرصة التشجير',
            EmailLog::query()->where('template_key', 'certificate.ready')->value('subject'),
        );
    }

    public function test_designer_lists_only_the_modes_and_fields_for_the_activity_type(): void
    {
        $admin = $this->admin();
        $path = $this->path();
        $opportunity = $this->opportunity(6);
        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($admin)
            ->test(ManagePathCertificateDesign::class, ['record' => $path->id])
            ->assertOk()
            ->assertSee('إكمال كل الدورات')
            ->assertSee('عدد الدورات المكتملة')
            ->assertDontSee('إصدار تلقائي عند تحقق الشروط')
            ->assertDontSee('الساعات التطوعية المعتمدة');

        Livewire::actingAs($admin)
            ->test(ManageVolunteerCertificateDesign::class, ['record' => $opportunity->id])
            ->assertOk()
            ->assertSee('حد الساعات المعتمدة')
            ->assertSee('الساعات التطوعية المعتمدة')
            ->assertDontSee('إكمال كل الدورات')
            ->assertDontSee('عدد الدورات المكتملة');
    }

    public function test_volunteer_backfill_keeps_expected_hours_without_auto_issue(): void
    {
        $opportunity = $this->opportunity(30);

        app(CertificateTemplateBackfill::class)->backfillVolunteerOpportunities();

        $template = $opportunity->certificateTemplate()->first();
        $this->assertNotNull($template);
        $this->assertFalse($template->auto_issue);
        $this->assertSame(CertificateEligibilityMode::MinApprovedHours, $template->eligibility->mode);
        $this->assertSame(30.0, $template->eligibility->minApprovedHours);
    }

    public function test_backfilled_path_certificate_can_still_be_downloaded_and_verified(): void
    {
        $user = $this->beneficiary('شهادة مهاجرة');
        $user->assignRole('beneficiary');
        $path = $this->path();
        $relative = 'certificates/migrated-'.uniqid().'.pdf';
        Storage::disk('public')->put($relative, '%PDF-1.4 migrated');
        $certificate = Certificate::query()->create([
            'user_id' => $user->id,
            'certificateable_type' => $path->getMorphClass(),
            'certificateable_id' => $path->id,
            'certificate_number' => 'CERT-PATH-'.uniqid(),
            'verification_code' => bin2hex(random_bytes(8)),
            'file_path' => $relative,
            'pdf_status' => CertificatePdfStatus::Generated,
            'issued_at' => now(),
            'data_snapshot' => [
                'recipient_name' => 'مستفيد مهاجر',
                'activity_title' => $path->title,
            ],
        ]);

        app(CertificateTemplateBackfill::class)->backfillLearningPaths();

        $certificate->refresh();
        $template = $path->certificateTemplate()->first();
        $this->assertNotNull($certificate->certificate_template_id);
        $this->assertFalse($template?->auto_issue);
        $this->assertSame(CertificateEligibilityMode::CompletedAllCourses, $template?->eligibility->mode);

        $this->actingAsOtpVerified($user)
            ->get(route('certificates.download', $certificate))
            ->assertOk();

        $this->get(route('certificates.verify', $certificate->verification_code))
            ->assertOk()
            ->assertSee('شهادة صحيحة')
            ->assertSee('مستفيد مهاجر')
            ->assertSee($path->title);
    }

    private function admin(): User
    {
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    private function beneficiary(string $name): User
    {
        return User::factory()->create([
            'name' => $name,
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
            'notification_prefs_set_at' => now(),
        ]);
    }

    private function path(): LearningPath
    {
        return LearningPath::query()->create([
            'title' => 'مسار الشهادات '.uniqid(),
            'slug' => 'cert-path-'.uniqid(),
            'status' => PathStatus::Published,
            'published_at' => now(),
        ]);
    }

    private function publishedProgram(LearningPath $path): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => 'دورة '.uniqid(),
            'slug' => 'path-course-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => $path->id,
        ]);
    }

    private function opportunity(float $hours): VolunteerOpportunity
    {
        return VolunteerOpportunity::query()->create([
            'title' => 'فرصة الشهادات '.uniqid(),
            'slug' => 'cert-vol-'.uniqid(),
            'status' => OpportunityStatus::Published,
            'published_at' => now(),
            'hours_expected' => $hours,
        ]);
    }

    /**
     * @param  array<string, mixed>  $eligibility
     */
    private function readyTemplate(LearningPath|VolunteerOpportunity $owner, array $eligibility, bool $autoIssue): CertificateTemplate
    {
        return CertificateTemplate::query()->create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'status' => CertificateTemplateStatus::Ready,
            'auto_issue' => $autoIssue,
            'eligibility' => $eligibility,
            'elements' => [],
            'version' => 1,
        ]);
    }

    private function pathRegistration(LearningPath $path, User $user): PathRegistration
    {
        return PathRegistration::query()->create([
            'learning_path_id' => $path->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    private function completeProgram(TrainingProgram $program, User $user): void
    {
        ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Completed,
            'score' => 90,
        ]);
    }

    private function snapshotValue(?Certificate $certificate, string $key): ?string
    {
        $snapshot = is_array($certificate?->data_snapshot) ? $certificate->data_snapshot : [];
        $value = $snapshot[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }
}
