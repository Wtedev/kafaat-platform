<?php

namespace Tests\Feature\Certificates;

use App\Enums\CertificatePdfStatus;
use App\Enums\CertificateTemplateStatus;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Filament\Resources\TrainingProgramResource\Pages\ViewTrainingProgram;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramCertificatesRelationManager;
use App\Jobs\ExportCertificatesZipJob;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\EmailLog;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Certificates\CertificateIssuanceService;
use App\Services\Certificates\CertificateRenderer;
use App\Services\CertificateService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;
use ZipArchive;

class ProgramCertificatesTabTest extends TestCase
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

    public function test_tab_is_visible_only_to_authorized_users(): void
    {
        $admin = $this->admin();
        $outsider = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $outsider->assignRole('staff');
        $program = $this->program();

        $this->actingAs($admin);
        $this->assertTrue(ProgramCertificatesRelationManager::canViewForRecord($program, ViewTrainingProgram::class));

        $this->actingAs($outsider);
        $this->assertFalse(ProgramCertificatesRelationManager::canViewForRecord($program, ViewTrainingProgram::class));
    }

    public function test_certificates_tab_has_no_issuance_action(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $this->registration($program, 'بلا قالب', 90);
        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($admin)
            ->test(ProgramCertificatesRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->assertDontSee('إصدار الشهادات للمؤهلين')
            ->assertDontSee('إصدار استثنائي')
            ->assertSee('لم يُعتمد تصميم الشهادة بعد');

        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_revoke_shows_on_verification_and_hides_the_certificate_from_the_portal(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $this->readyTemplate($program, 60);
        $beneficiary = User::factory()->create([
            'name' => 'مستفيد بوابة',
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
            'notification_prefs_set_at' => now(),
        ]);
        $beneficiary->assignRole('beneficiary');
        $registration = ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $beneficiary->id,
            'status' => RegistrationStatus::Approved,
            'score' => 90,
        ]);
        $certificate = app(CertificateIssuanceService::class)->markEligible($registration, $admin);
        $this->assertInstanceOf(Certificate::class, $certificate);

        app(CertificateIssuanceService::class)->revoke($certificate, $admin, 'بيانات غير صحيحة');

        $this->get(route('certificates.verify', $certificate->verification_code))
            ->assertOk()
            ->assertSee('هذه الشهادة ملغاة')
            ->assertDontSee($beneficiary->email);

        $this->actingAsOtpVerified($beneficiary)
            ->get(route('portal.certificates'))
            ->assertOk()
            ->assertDontSee($certificate->certificate_number);

        $this->actingAsOtpVerified($beneficiary)
            ->get(route('certificates.download', $certificate))
            ->assertNotFound();

        $reissued = app(CertificateIssuanceService::class)->markEligible($registration->fresh(), $admin);
        $this->assertNotSame($certificate->id, $reissued?->id);
        $this->assertSame(1, Certificate::query()->active()->count());
    }

    public function test_portal_shows_pending_state_and_requirement_progress(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $program = $this->program();
        $this->readyTemplate($program, 80);
        $beneficiary = User::factory()->create([
            'name' => 'متدرب الشروط',
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
            'notification_prefs_set_at' => now(),
        ]);
        $beneficiary->assignRole('beneficiary');
        $registration = ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $beneficiary->id,
            'status' => RegistrationStatus::Approved,
            'score' => 70,
        ]);

        $this->actingAsOtpVerified($beneficiary)
            ->get(route('portal.programs'))
            ->assertOk()
            ->assertSee('درجتك 70 — المطلوب 80');

        $certificate = app(CertificateIssuanceService::class)->markEligible($registration, $admin);
        $this->assertSame(CertificatePdfStatus::Pending, $certificate->pdf_status);

        $this->actingAsOtpVerified($beneficiary)
            ->get(route('portal.certificates'))
            ->assertOk()
            ->assertSee('جارٍ تجهيز الشهادة')
            ->assertSee($certificate->certificate_number);
    }

    public function test_zip_contains_the_certificate_and_the_link_expires(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $this->readyTemplate($program, 60);
        $registration = $this->registration($program, 'ملف مضغوط', 88);
        $certificate = app(CertificateIssuanceService::class)->markEligible($registration, $admin);
        $this->assertSame(CertificatePdfStatus::Generated, $certificate?->fresh()->pdf_status);

        (new ExportCertificatesZipJob($program->id, $admin->id))->handle();

        $notification = $admin->notifications()->first();
        $url = $notification?->data['actions'][0]['url'] ?? null;
        $this->assertIsString($url);

        $zipPath = collect(Storage::disk('local')->allFiles('certificate-exports'))->first();
        $this->assertNotNull($zipPath);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($zipPath)) === true);
        $this->assertSame(1, $zip->numFiles);
        $this->assertSame('ملف مضغوط-'.$certificate->certificate_number.'.pdf', $zip->getNameIndex(0));
        $zip->close();

        $this->actingAs($admin)->withSession(['otp_verified' => true])->get($url)->assertOk();
        $this->travel(25)->hours();
        $this->actingAs($admin)->withSession(['otp_verified' => true])->get($url)->assertForbidden();
    }

    public function test_expired_zip_files_are_purged(): void
    {
        Storage::disk('local')->put('certificate-exports/old.zip', 'old');
        Storage::disk('local')->put('certificate-exports/new.zip', 'new');
        touch(Storage::disk('local')->path('certificate-exports/old.zip'), now()->subHours(30)->getTimestamp());

        $this->artisan('certificates:purge-expired-exports')->assertSuccessful();

        Storage::disk('local')->assertMissing('certificate-exports/old.zip');
        Storage::disk('local')->assertExists('certificate-exports/new.zip');
    }

    public function test_certificate_email_is_not_sent_twice(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $program = $this->program();
        $this->readyTemplate($program, 60);
        $registration = $this->registration($program, 'بريد', 91);
        $certificate = app(CertificateIssuanceService::class)->markEligible($registration, $admin);
        $this->assertInstanceOf(Certificate::class, $certificate);

        $mailer = app(CertificateService::class);
        $this->assertTrue($mailer->emailCertificate($certificate->fresh(), $admin));
        $this->assertFalse($mailer->emailCertificate($certificate->fresh(), $admin));
        $this->assertSame(1, EmailLog::query()->where('template_key', 'certificate.ready')->count());
        $this->assertNotNull($certificate->fresh()->emailed_at);
    }

    public function test_table_query_count_stays_constant_for_five_and_fifty_beneficiaries(): void
    {
        $admin = $this->admin();
        $small = $this->program();
        $large = $this->program();
        $this->readyTemplate($small, 60);
        $this->readyTemplate($large, 60);

        foreach (range(1, 5) as $index) {
            $this->registration($small, 'صغير '.$index, 80);
        }
        foreach (range(1, 50) as $index) {
            $this->registration($large, 'كبير '.$index, 80);
        }

        $warm = $this->program();
        $this->readyTemplate($warm, 60);
        $this->registration($warm, 'تهيئة', 80);
        $this->withSession(['otp_verified' => true]);
        $this->renderQueries($admin, $warm);

        $small->unsetRelation('certificateTemplate');
        $large->unsetRelation('certificateTemplate');
        $smallQueries = count($this->renderQueries($admin, $small));
        $large->unsetRelation('certificateTemplate');
        $largeQueries = count($this->renderQueries($admin, $large));

        $this->assertSame($smallQueries, $largeQueries);
    }

    public function test_failed_transaction_does_not_send_a_notification(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $program = $this->program();
        $this->readyTemplate($program, 60);
        $registration = $this->registration($program, 'تراجع', 95);
        $inboxBefore = DB::table('in_app_notifications')->count();
        $notificationsBefore = DB::table('notifications')->count();

        try {
            DB::transaction(function () use ($registration, $admin): void {
                app(CertificateIssuanceService::class)->markEligible($registration, $admin);
                throw new RuntimeException('fail');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, Certificate::query()->count());
        $this->assertSame($inboxBefore, DB::table('in_app_notifications')->count());
        $this->assertSame($notificationsBefore, DB::table('notifications')->count());
        Queue::assertNothingPushed();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function renderQueries(User $admin, TrainingProgram $program): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::actingAs($admin)
            ->test(ProgramCertificatesRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->assertSee('مؤهل');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        return $queries;
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

    private function program(?User $owner = null): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => 'برنامج الشهادات '.uniqid(),
            'slug' => 'cert-tab-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'owner_id' => $owner?->id,
            'created_by' => $owner?->id,
        ]);
    }

    private function readyTemplate(TrainingProgram $program, float $minScore): CertificateTemplate
    {
        $path = 'certificate-backgrounds/tab-'.uniqid().'.png';
        Storage::disk('local')->put($path, 'png');

        return CertificateTemplate::query()->create([
            'owner_type' => $program->getMorphClass(),
            'owner_id' => $program->id,
            'background_disk' => 'local',
            'background_path' => $path,
            'status' => CertificateTemplateStatus::Ready,
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

    private function registration(TrainingProgram $program, string $name, float $score): ProgramRegistration
    {
        $user = User::factory()->create([
            'name' => $name,
            'role_type' => 'beneficiary',
            'is_active' => true,
        ]);

        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Approved,
            'score' => $score,
        ]);
    }
}
