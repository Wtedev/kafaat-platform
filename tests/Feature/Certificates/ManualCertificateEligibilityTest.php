<?php

namespace Tests\Feature\Certificates;

use App\Enums\CertificateTemplateStatus;
use App\Enums\OpportunityStatus;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\VolunteerHoursStatus;
use App\Filament\Resources\TrainingProgramResource\Pages\ViewTrainingProgram;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramCertificatesRelationManager;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramRegistrationsRelationManager;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\InboxNotification;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerHour;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
use App\Services\Certificates\CertificateIssuanceService;
use App\Services\Certificates\CertificateTemplateBackfill;
use App\Services\Certificates\CertificateRenderer;
use App\Services\ProgramRegistrationService;
use App\Services\VolunteerHoursService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class ManualCertificateEligibilityTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('local');
        $this->mock(CertificateRenderer::class, function ($mock): void {
            $mock->shouldReceive('render')->andReturn('%PDF-1.4');
        });
    }

    public function test_completing_a_registration_or_approving_hours_does_not_issue_a_certificate(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $this->designedTemplate($program);
        $registration = $this->registration($program, 'مكتمل', 10);

        app(ProgramRegistrationService::class)->markCompleted($registration, $admin, 95, 100);

        $opportunity = VolunteerOpportunity::query()->create([
            'title' => 'فرصة يدوية',
            'slug' => 'manual-vol-'.uniqid(),
            'status' => OpportunityStatus::Published,
            'published_at' => now(),
            'hours_expected' => 4,
        ]);
        $this->designedTemplate($opportunity);
        $volunteer = User::factory()->create(['role_type' => 'beneficiary', 'is_active' => true]);
        VolunteerRegistration::query()->create([
            'user_id' => $volunteer->id,
            'opportunity_id' => $opportunity->id,
            'status' => RegistrationStatus::Approved,
        ]);
        $hours = VolunteerHour::query()->create([
            'user_id' => $volunteer->id,
            'opportunity_id' => $opportunity->id,
            'hours' => 4,
            'status' => VolunteerHoursStatus::Pending,
        ]);
        app(VolunteerHoursService::class)->approveHours($hours, $admin);

        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_admin_can_issue_manually_without_meeting_the_eligibility_rule(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $this->designedTemplate($program, minScore: 90);
        $registration = $this->registration($program, 'درجة منخفضة', 10);
        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($admin)
            ->test(ProgramRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->assertSee('نسبة الحضور')
            ->assertSee('الدرجة')
            ->assertSee('الساعات المعتمدة')
            ->callAction(TestAction::make('markCertificateEligible')->table($registration));

        $certificate = Certificate::query()->where('user_id', $registration->user_id)->first();
        $this->assertInstanceOf(Certificate::class, $certificate);
        $this->assertTrue(
            Activity::query()
                ->where('event', 'certificate_marked_eligible')
                ->where('causer_id', $admin->id)
                ->where('subject_id', $certificate->id)
                ->exists()
        );
        $this->assertTrue(
            InboxNotification::query()->where('user_id', $registration->user_id)->exists()
        );

        $this->actingAsOtpVerified($registration->user)
            ->get(route('portal.certificates'))
            ->assertOk()
            ->assertSee($program->title);
    }

    public function test_bulk_issue_marks_every_selected_registration(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $this->designedTemplate($program, minScore: 90);
        $first = $this->registration($program, 'أول', 5);
        $second = $this->registration($program, 'ثان', 6);
        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($admin)
            ->test(ProgramRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->selectTableRecords([$first->id, $second->id])
            ->callAction(TestAction::make('markCertificateEligibleBulk')->table()->bulk());

        $this->assertSame(2, Certificate::query()->count());
    }

    public function test_backfilled_template_stays_a_draft_and_cannot_be_issued(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $registration = $this->registration($program, 'مسودة الترحيل', 100);

        app(CertificateTemplateBackfill::class)->backfillTrainingPrograms();

        $template = $program->certificateTemplate()->first();
        $this->assertNotNull($template);
        $this->assertSame(CertificateTemplateStatus::Draft, $template->status);
        $this->assertNotSame([], app(CertificateIssuanceService::class)->designGaps($template));

        try {
            app(CertificateIssuanceService::class)->markEligible($registration, $admin);
            $this->fail('A backfilled draft must not be issued.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('لا يمكن إصدار الشهادة قبل حفظ التصميم', $exception->getMessage());
        }

        $this->assertSame(0, Certificate::query()->count());

        $this->actingAs($admin);

        Livewire::test(ProgramCertificatesRelationManager::class, [
            'ownerRecord' => $program,
            'pageClass' => ViewTrainingProgram::class,
        ])
            ->assertSee('لم يُعتمد تصميم الشهادة بعد')
            ->assertDontSee('بلغ متوسط حضوره ودرجته 75% فأكثر');

        $template->update(['status' => CertificateTemplateStatus::Ready]);

        Livewire::test(ProgramCertificatesRelationManager::class, [
            'ownerRecord' => $program->fresh(),
            'pageClass' => ViewTrainingProgram::class,
        ])
            ->assertSee('لم يُعتمد تصميم الشهادة بعد')
            ->assertDontSee('بلغ متوسط حضوره ودرجته 75% فأكثر');
    }

    public function test_issue_is_blocked_when_the_template_has_no_saved_design(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        CertificateTemplate::query()->create([
            'owner_type' => $program->getMorphClass(),
            'owner_id' => $program->id,
            'status' => 'ready',
            'eligibility' => [
                'mode' => 'score_only',
                'min_score' => 1,
                'require_completed_status' => false,
                'require_activity_ended' => false,
            ],
            'elements' => [],
            'version' => 1,
        ]);
        $registration = $this->registration($program, 'بلا تصميم', 100);

        try {
            app(CertificateIssuanceService::class)->markEligible($registration, $admin);
            $this->fail('إصدار بلا تصميم كان يجب أن يُرفض.');
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->implode(' ');
            $this->assertStringContainsString('لا يمكن إصدار الشهادة قبل حفظ التصميم', $message);
        }

        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_revoke_requires_a_reason_and_shows_cancelled_on_verification(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $this->designedTemplate($program);
        $registration = $this->registration($program, 'يُلغى', 20);
        $certificate = app(CertificateIssuanceService::class)->markEligible($registration, $admin);
        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($admin)
            ->test(ProgramRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->callAction(TestAction::make('revokeCertificateEligibility')->table($registration), [
                'reason' => 'طلب الإدارة',
            ]);

        $certificate->refresh();
        $this->assertNotNull($certificate->revoked_at);
        $this->assertTrue(
            Activity::query()
                ->where('event', 'certificate_revoked')
                ->where('causer_id', $admin->id)
                ->where('properties->revoke_reason', 'طلب الإدارة')
                ->exists()
        );

        $this->get(route('certificates.verify', $certificate->verification_code))
            ->assertOk()
            ->assertSee('ملغاة');
    }

    public function test_only_admin_or_the_dedicated_permission_can_mark_eligible(): void
    {
        $program = $this->program();
        $this->designedTemplate($program);
        $registration = $this->registration($program, 'صلاحية', 20);
        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole('staff');
        $service = app(CertificateIssuanceService::class);

        $this->assertFalse($service->canDecide($staff));
        try {
            $service->markEligible($registration, $staff);
            $this->fail('الموظف بلا الصلاحية كان يجب أن يُرفض.');
        } catch (ValidationException) {
            $this->assertSame(0, Certificate::query()->count());
        }

        $staff->givePermissionTo('certificates.mark_eligible');
        $this->assertTrue($service->canDecide($staff->fresh()));
        $certificate = $service->markEligible($registration->fresh(), $staff->fresh());
        $this->assertInstanceOf(Certificate::class, $certificate);
        $this->assertTrue($service->canDecide($this->admin()));
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

    private function program(): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => 'برنامج التأهيل اليدوي '.uniqid(),
            'slug' => 'manual-cert-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
        ]);
    }

    private function registration(TrainingProgram $program, string $name, float $score): ProgramRegistration
    {
        $user = User::factory()->create([
            'name' => $name,
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('beneficiary');

        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Approved,
            'score' => $score,
            'attendance_percentage' => 20,
        ]);
    }

    private function designedTemplate(TrainingProgram|VolunteerOpportunity $owner, float $minScore = 60): CertificateTemplate
    {
        $path = 'certificate-backgrounds/manual-'.uniqid().'.png';
        Storage::disk('local')->put($path, 'png');

        return CertificateTemplate::query()->create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'background_disk' => 'local',
            'background_path' => $path,
            'status' => 'ready',
            'eligibility' => [
                'mode' => 'score_only',
                'min_score' => $minScore,
                'require_completed_status' => true,
                'require_activity_ended' => false,
            ],
            'elements' => [[
                'id' => (string) Str::uuid(),
                'type' => 'field',
                'key' => 'recipient_name',
                'text' => null,
                'prefix' => null,
                'suffix' => null,
                'x' => 10,
                'y' => 10,
                'width' => 40,
                'height' => 8,
                'font_family' => 'ibmplexsansarabic',
                'font_size_pt' => 18,
                'font_weight' => 'regular',
                'color' => '#1a1a1a',
                'align' => 'center',
                'auto_shrink' => false,
                'min_font_size_pt' => null,
                'image_path' => null,
            ]],
            'version' => 1,
        ]);
    }
}
