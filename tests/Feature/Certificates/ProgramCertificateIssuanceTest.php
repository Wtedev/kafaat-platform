<?php

namespace Tests\Feature\Certificates;

use App\Enums\AttendanceMarkSource;
use App\Enums\InboxNotificationType;
use App\Enums\OpportunityStatus;
use App\Enums\PathStatus;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SurveyType;
use App\Filament\Resources\TrainingProgramResource\Pages\ViewTrainingProgram;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramRegistrationCertificatesRelationManager;
use App\Jobs\IssueProgramCertificateJob;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\EmailLog;
use App\Models\InboxNotification;
use App\Models\LearningPath;
use App\Models\ProgramAttendanceLink;
use App\Models\ProgramAttendanceMark;
use App\Models\ProgramRegistration;
use App\Models\ProgramSurvey;
use App\Models\SurveyCompletion;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Notifications\CertificateReadyEmail;
use App\Notifications\InboxNotificationEmail;
use App\Services\Certificates\ProgramCertificateIssuance;
use App\Services\CertificateService;
use App\Services\EmailLogService;
use App\Support\Certificates\CertificateBeneficiaryCount;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ReflectionMethod;
use RuntimeException;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class ProgramCertificateIssuanceTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    private static bool $armCertificateRace = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_beneficiary_count_uses_the_arabic_dual_and_plural(): void
    {
        $this->assertSame('إصدار الشهادات لـ (0) مستفيدين', CertificateBeneficiaryCount::buttonLabel(0));
        $this->assertSame('إصدار الشهادات لـ (1) مستفيد', CertificateBeneficiaryCount::buttonLabel(1));
        $this->assertSame('إصدار الشهادات لـ (2) مستفيدَين', CertificateBeneficiaryCount::buttonLabel(2));
        $this->assertSame('إصدار الشهادات لـ (3) مستفيدين', CertificateBeneficiaryCount::buttonLabel(3));
        $this->assertSame('إصدار الشهادات لـ (10) مستفيدين', CertificateBeneficiaryCount::buttonLabel(10));
        $this->assertSame('إصدار الشهادات لـ (11) مستفيد', CertificateBeneficiaryCount::buttonLabel(11));
    }

    public function test_issuing_twice_creates_one_certificate_and_sends_one_attached_email(): void
    {
        Storage::fake('public');
        Notification::fake();

        [$program, $registration, $beneficiary, $issuer] = $this->approvedRegistration();
        $this->saveBackground($program);
        $beneficiary->forceFill(['notify_email' => false])->save();

        $job = new IssueProgramCertificateJob($registration->id, $issuer->id);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('program-certificate:'.$registration->id, $job->uniqueId());
        $job->handle(app(ProgramCertificateIssuance::class));
        $job->handle(app(ProgramCertificateIssuance::class));

        $this->assertSame(1, Certificate::query()->count());
        $certificate = Certificate::query()->firstOrFail();
        $this->assertNotNull($certificate->emailed_at);
        $this->assertNotNull($certificate->file_path);
        Storage::disk('public')->assertExists($certificate->file_path);
        $this->assertSame(RegistrationStatus::Completed, $registration->fresh()->status);
        $this->assertSame(1, EmailLog::query()->where('template_key', 'certificate.program_ready')->where('status', 'sent')->count());
        $this->assertSame(1, InboxNotification::query()->where('type', InboxNotificationType::CertificateIssued)->count());

        Notification::assertSentToTimes($beneficiary, CertificateReadyEmail::class, 1);
        Notification::assertSentTo($beneficiary, CertificateReadyEmail::class, function (CertificateReadyEmail $notification) use ($beneficiary, $program, $certificate): bool {
            $mail = $notification->toMail($beneficiary);

            return $mail->subject === 'تم إصدار شهادتك — '.$program->title
                && $notification->attachPdf === true
                && $notification->buttonLabel === 'عرض الشهادة'
                && $notification->buttonUrl === route('portal.certificates')
                && count($mail->attachments) === 1
                && $mail->attachments[0]['options']['mime'] === 'application/pdf'
                && str_contains($mail->attachments[0]['file'], $certificate->certificate_number);
        });

        $legacy = (new CertificateReadyEmail($certificate, $program->title))->toMail($beneficiary);
        $this->assertSame('شهادتك جاهزة — '.$program->title, $legacy->subject);
        $this->assertSame([], $legacy->attachments);

        $this->actingAsOtpVerified($beneficiary)
            ->get(route('portal.certificates'))
            ->assertOk()
            ->assertSee($certificate->certificate_number)
            ->assertSee('تحميل الشهادة');

        $this->get(route('certificates.verify', $certificate->verification_code))
            ->assertOk()
            ->assertSee('شهادة صحيحة')
            ->assertSee($certificate->certificate_number);
    }

    public function test_a_missing_pdf_is_regenerated_and_the_inbox_is_sent_once(): void
    {
        Storage::fake('public');
        Notification::fake();

        [$program, $registration, $beneficiary, $issuer] = $this->approvedRegistration();
        $this->saveBackground($program);
        $beneficiary->forceFill(['notify_email' => false])->save();

        $existing = Certificate::query()->create([
            'user_id' => $beneficiary->id,
            'certificateable_type' => TrainingProgram::class,
            'certificateable_id' => $program->id,
            'certificate_number' => 'CERT-MISSING-PDF',
            'verification_code' => bin2hex(random_bytes(16)),
            'issued_at' => now(),
            'file_path' => null,
        ]);

        (new IssueProgramCertificateJob($registration->id, $issuer->id))
            ->handle(app(ProgramCertificateIssuance::class));

        $existing->refresh();
        $this->assertNotNull($existing->file_path);
        Storage::disk('public')->assertExists($existing->file_path);
        $bytes = Storage::disk('public')->get($existing->file_path);
        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertStringContainsString('DCTDecode', $bytes);
        $this->assertNotNull($existing->emailed_at);
        $this->assertSame(1, Certificate::query()->count());
        $this->assertSame(1, InboxNotification::query()->where('type', InboxNotificationType::CertificateIssued)->count());
        Notification::assertSentToTimes($beneficiary, CertificateReadyEmail::class, 1);
    }

    public function test_a_failed_email_is_retried_without_a_second_certificate(): void
    {
        Storage::fake('public');

        [$program, $registration, $beneficiary, $issuer] = $this->approvedRegistration();
        $this->saveBackground($program);
        $beneficiary->forceFill(['notify_email' => false])->save();
        $attempts = 0;

        $this->mock(EmailLogService::class, function ($mock) use (&$attempts): void {
            $mock->shouldReceive('send')->andReturnUsing(function (User $recipient, $notification, string $templateKey, string $subject, ?User $sentBy) use (&$attempts): void {
                $attempts++;
                EmailLog::query()->create([
                    'recipient_email' => $recipient->email,
                    'subject' => $subject,
                    'template_key' => $templateKey,
                    'status' => $attempts === 1 ? 'failed' : 'sent',
                    'sent_by' => $sentBy?->id,
                    'sent_at' => now(),
                ]);
            });
        });

        $issuance = app(ProgramCertificateIssuance::class);

        try {
            (new IssueProgramCertificateJob($registration->id, $issuer->id))->handle($issuance);
            $this->fail('The failed email should be retried.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Program certificate email was not sent.', $exception->getMessage());
        }

        $this->assertSame(1, Certificate::query()->count());
        $this->assertNull(Certificate::query()->firstOrFail()->emailed_at);

        (new IssueProgramCertificateJob($registration->id, $issuer->id))->handle($issuance);

        $this->assertSame(1, Certificate::query()->count());
        $this->assertNotNull(Certificate::query()->firstOrFail()->emailed_at);
        $this->assertSame(1, EmailLog::query()->where('status', 'sent')->count());
        $this->assertSame(1, EmailLog::query()->where('status', 'failed')->count());
    }

    public function test_a_unique_violation_returns_the_winning_certificate_without_a_second_inbox(): void
    {
        Storage::fake('public');
        [$program, $registration, $beneficiary, $issuer] = $this->approvedRegistration();

        // Another worker inserts the same certificate right after issue() checks for an
        // existing one and before it inserts its own. The row is written outside issue()'s
        // insert, as a second worker's committed row would be.
        self::$armCertificateRace = true;
        DB::listen(function ($query) use ($beneficiary, $program): void {
            if (! self::$armCertificateRace
                || ! str_starts_with(strtolower(ltrim($query->sql)), 'select')
                || ! str_contains($query->sql, 'certificates')) {
                return;
            }

            self::$armCertificateRace = false;
            DB::table('certificates')->insert([
                'user_id' => $beneficiary->id,
                'certificateable_type' => $program->getMorphClass(),
                'certificateable_id' => $program->id,
                'certificate_number' => 'CERT-RACE-WINNER',
                'verification_code' => bin2hex(random_bytes(16)),
                'issued_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $certificate = app(CertificateService::class)->issue($beneficiary, $program, $issuer);

        $this->assertSame('CERT-RACE-WINNER', $certificate->certificate_number);
        $this->assertSame(1, Certificate::query()->count());
        $this->assertSame(0, InboxNotification::query()->count());
        $this->assertNull($certificate->file_path);
    }

    public function test_the_tab_queues_one_job_per_eligible_person_and_hides_the_old_buttons(): void
    {
        Storage::fake('public');
        Queue::fake();

        $program = $this->program();
        $this->saveBackground($program);
        $first = $this->beneficiary('أحمد');
        $second = $this->beneficiary('سارة');
        $pending = $this->beneficiary('خالد');
        $this->registration($program, $first, RegistrationStatus::Approved);
        $this->registration($program, $second, RegistrationStatus::Completed);
        $this->registration($program, $pending, RegistrationStatus::Pending);

        $early = $this->attendanceLink($program, 'اليوم الأول', '2026-10-01 09:00:00');
        $this->attendanceLink($program, 'اليوم الثاني', '2026-10-02 09:00:00');
        $this->attendanceLink($program, 'يوم بلا موعد', null);
        ProgramAttendanceLink::query()->create([
            'training_program_id' => $program->id,
            'name' => 'يوم ملغى',
            'opens_at' => '2026-10-03 09:00:00',
            'cancelled_at' => now(),
            'open_minutes' => 30,
        ]);

        ProgramSurvey::query()->create([
            'training_program_id' => $program->id,
            'type' => SurveyType::Pre,
            'is_anonymous' => false,
        ]);
        $satisfaction = ProgramSurvey::query()->create([
            'training_program_id' => $program->id,
            'type' => SurveyType::Satisfaction,
            'is_anonymous' => true,
        ]);
        SurveyCompletion::query()->create([
            'program_survey_id' => $satisfaction->id,
            'registration_id' => ProgramRegistration::query()->where('user_id', $first->id)->value('id'),
        ]);

        $firstRegistration = ProgramRegistration::query()->where('user_id', $first->id)->firstOrFail();
        ProgramAttendanceMark::query()->create([
            'program_attendance_link_id' => $early->id,
            'program_registration_id' => $firstRegistration->id,
            'attended_at' => now(),
            'source' => AttendanceMarkSource::Manual,
        ]);

        $staff = $this->staff('certificates.issue');
        DB::enableQueryLog();

        $component = Livewire::actingAs($staff)
            ->test(ProgramRegistrationCertificatesRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ]);

        $component
            ->assertSeeHtml('المقبولون: <strong>2</strong>')
            ->assertSeeHtml('الصادرة: <strong>0</strong>')
            ->assertSeeHtml('المتبقون: <strong>2</strong>')
            ->assertSee('أحمد')
            ->assertSee('سارة')
            ->assertDontSee('خالد')
            ->assertSee('لم تصدر')
            ->assertSee('1 من 3')
            ->assertSee('0 من 3')
            ->assertSee('قبلي ✗')
            ->assertSee('رضا ✓')
            ->assertDontSee('إصدار الشهادات للمؤهلين')
            ->assertDontSee('إرسال إيميلات الشهادات');

        $certificateQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'certificates'))
            ->count();
        $this->assertLessThanOrEqual(4, $certificateQueries);

        $issuer = $this->staff('issue_certificates');
        $issuerComponent = Livewire::actingAs($issuer)
            ->test(ProgramRegistrationCertificatesRelationManager::class, [
                'ownerRecord' => $program->fresh(),
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->assertActionVisible(TestAction::make('designCertificate')->table())
            ->assertActionVisible(TestAction::make('issueCertificates')->table())
            ->assertActionEnabled(TestAction::make('issueCertificates')->table())
            ->mountAction(TestAction::make('issueCertificates')->table());

        $mounted = $issuerComponent->instance()->getMountedAction();
        $this->assertNotNull($mounted);
        $this->assertSame('إصدار الشهادات لـ (2) مستفيدَين', $mounted->getModalSubmitActionLabel());
        $this->assertSame(['أحمد', 'سارة'], $issuerComponent->instance()->certificateIssueNames);
        $this->assertFalse($mounted->getModalSubmitAction()?->isDisabled() ?? true);

        $options = (new ReflectionMethod($issuerComponent->instance(), 'attendanceOptions'))->invoke($issuerComponent->instance());
        $this->assertSame(['اليوم الأول', 'اليوم الثاني', 'يوم بلا موعد'], array_values($options));

        $issuerComponent
            ->callMountedAction()
            ->assertNotified('تم إصدار 2 شهادة');

        Queue::assertPushed(IssueProgramCertificateJob::class, 2);

        $audit = AuditLog::query()->where('action', 'program_certificates.issued')->first();
        $this->assertNotNull($audit);
        $this->assertSame($issuer->id, $audit->actor_id);
        $this->assertSame($program->id, $audit->resource_id);
        $this->assertSame(2, $audit->metadata['count']);

        Queue::fake();
        $filtered = Livewire::actingAs($issuer)
            ->test(ProgramRegistrationCertificatesRelationManager::class, [
                'ownerRecord' => $program->fresh(),
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->mountAction(TestAction::make('issueCertificates')->table())
            ->setActionData([
                'attendance_link_ids' => [$early->id],
            ]);

        $this->assertSame(
            'إصدار الشهادات لـ (1) مستفيد',
            $filtered->instance()->getMountedAction()?->getModalSubmitActionLabel(),
        );
        $filtered->callMountedAction();

        Queue::assertPushed(IssueProgramCertificateJob::class, 1);
    }

    public function test_selected_rows_can_be_issued_and_filters_narrow_the_list(): void
    {
        Storage::fake('public');
        Queue::fake();

        $program = $this->program();
        $this->saveBackground($program);
        $full = $this->beneficiary('أحمد');
        $partial = $this->beneficiary('سارة');
        $absent = $this->beneficiary('خالد');
        $fullRegistration = $this->registration($program, $full, RegistrationStatus::Approved);
        $partialRegistration = $this->registration($program, $partial, RegistrationStatus::Approved);
        $absentRegistration = $this->registration($program, $absent, RegistrationStatus::Approved);

        $firstDay = $this->attendanceLink($program, 'اليوم الأول', '2026-10-01 09:00:00');
        $secondDay = $this->attendanceLink($program, 'اليوم الثاني', '2026-10-02 09:00:00');
        foreach ([$firstDay, $secondDay] as $day) {
            ProgramAttendanceMark::query()->create([
                'program_attendance_link_id' => $day->id,
                'program_registration_id' => $fullRegistration->id,
                'attended_at' => now(),
                'source' => AttendanceMarkSource::Manual,
            ]);
        }
        ProgramAttendanceMark::query()->create([
            'program_attendance_link_id' => $firstDay->id,
            'program_registration_id' => $partialRegistration->id,
            'attended_at' => now(),
            'source' => AttendanceMarkSource::Manual,
        ]);

        $pre = ProgramSurvey::query()->create([
            'training_program_id' => $program->id,
            'type' => SurveyType::Pre,
            'is_anonymous' => true,
        ]);
        SurveyCompletion::query()->create([
            'program_survey_id' => $pre->id,
            'registration_id' => $fullRegistration->id,
        ]);

        $issuer = $this->staff('certificates.issue');
        $component = Livewire::actingAs($issuer)
            ->test(ProgramRegistrationCertificatesRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->assertTableBulkActionExists('issueSelected')
            ->assertTableFilterExists('attended')
            ->assertTableFilterExists('attendance_percent')
            ->assertTableFilterExists('survey_pre')
            ->assertTableFilterExists('survey_post')
            ->assertTableFilterExists('survey_satisfaction');

        $component
            ->filterTable('attended', 'present')
            ->assertCanSeeTableRecords([$fullRegistration, $partialRegistration])
            ->assertCanNotSeeTableRecords([$absentRegistration])
            ->resetTableFilters()
            ->filterTable('attended', 'absent')
            ->assertCanSeeTableRecords([$absentRegistration])
            ->assertCanNotSeeTableRecords([$fullRegistration, $partialRegistration])
            ->resetTableFilters()
            ->filterTable('attendance_percent', ['minimum' => 100])
            ->assertCanSeeTableRecords([$fullRegistration])
            ->assertCanNotSeeTableRecords([$partialRegistration, $absentRegistration])
            ->resetTableFilters()
            ->filterTable('survey_pre', 'done')
            ->assertCanSeeTableRecords([$fullRegistration])
            ->assertCanNotSeeTableRecords([$partialRegistration, $absentRegistration]);

        $component
            ->resetTableFilters()
            ->callTableBulkAction('issueSelected', [$partialRegistration])
            ->assertNotified('تم إصدار 1 شهادة للمحددين');

        Queue::assertPushed(IssueProgramCertificateJob::class, 1);
        Queue::assertPushed(
            IssueProgramCertificateJob::class,
            fn (IssueProgramCertificateJob $job): bool => $job->registrationId === $partialRegistration->id,
        );
    }

    public function test_a_local_public_disk_issues_the_certificate_in_the_request(): void
    {
        Storage::fake('public');
        Notification::fake();
        config(['queue.default' => 'database']);

        [$program, $registration, $beneficiary] = $this->approvedRegistration();
        $this->saveBackground($program);
        $beneficiary->forceFill(['notify_email' => false])->save();
        $issuer = $this->staff('certificates.issue');

        Livewire::actingAs($issuer)
            ->test(ProgramRegistrationCertificatesRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->callTableBulkAction('issueSelected', [$registration])
            ->assertNotified('تم إصدار 1 شهادة للمحددين');

        $this->assertSame(1, Certificate::query()->count());
        $this->assertSame(0, DB::table('jobs')->count());
        Storage::disk('public')->assertExists(Certificate::query()->firstOrFail()->file_path);
    }

    public function test_resend_sends_the_program_email_again(): void
    {
        Storage::fake('public');
        Notification::fake();

        [$program, $registration, $beneficiary, $issuer] = $this->approvedRegistration();
        $beneficiary->forceFill(['notify_email' => false])->save();
        $this->saveBackground($program);

        (new IssueProgramCertificateJob($registration->id, $issuer->id))
            ->handle(app(ProgramCertificateIssuance::class));

        Livewire::actingAs($issuer)
            ->test(ProgramRegistrationCertificatesRelationManager::class, [
                'ownerRecord' => $program->fresh(),
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->assertTableActionVisible('resendCertificateEmail', $registration)
            ->assertTableActionVisible('downloadCertificate', $registration)
            ->mountTableAction('resendCertificateEmail', $registration)
            ->callMountedTableAction()
            ->assertNotified('أُعيد إرسال البريد');

        Notification::assertSentToTimes($beneficiary, CertificateReadyEmail::class, 2);
        $this->assertSame(1, Certificate::query()->count());
    }

    public function test_issue_is_disabled_until_a_background_file_exists_and_hidden_without_permission(): void
    {
        Storage::fake('public');
        $program = $this->program();
        $staff = $this->staff();

        Livewire::actingAs($staff)
            ->test(ProgramRegistrationCertificatesRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->assertActionHidden(TestAction::make('issueCertificates')->table())
            ->assertActionHidden(TestAction::make('designCertificate')->table());

        $allowed = $this->staff('certificates.issue');
        CertificateTemplate::query()->create([
            'training_program_id' => $program->id,
            'background_path' => 'certificate-templates/missing.png',
            'elements' => [],
        ]);

        $component = Livewire::actingAs($allowed)
            ->test(ProgramRegistrationCertificatesRelationManager::class, [
                'ownerRecord' => $program->fresh(),
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->assertActionDisabled(TestAction::make('issueCertificates')->table());

        $disabled = collect($component->instance()->getTable()->getHeaderActions())
            ->first(fn ($action): bool => $action->getName() === 'issueCertificates');
        // A saved design whose file is gone asks staff to upload it again.
        $this->assertSame('ملف تصميم الشهادة مفقود، أعد رفعه', $disabled?->getTooltip());

        $this->saveBackground($program->fresh());

        Livewire::actingAs($allowed)
            ->test(ProgramRegistrationCertificatesRelationManager::class, [
                'ownerRecord' => $program->fresh(),
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->assertActionEnabled(TestAction::make('issueCertificates')->table());
    }

    /**
     * @return array{0: TrainingProgram, 1: ProgramRegistration, 2: User, 3: User}
     */
    private function approvedRegistration(): array
    {
        $program = $this->program();
        $beneficiary = $this->beneficiary('عبدالعزيز');
        $registration = $this->registration($program, $beneficiary, RegistrationStatus::Approved);
        $issuer = $this->staff('certificates.issue');

        return [$program, $registration, $beneficiary, $issuer];
    }

    private function program(): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => 'برنامج الشهادات',
            'slug' => 'program-certificates-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-12',
        ]);
    }

    private function beneficiary(string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'role_type' => 'beneficiary',
            'is_active' => true,
            'notify_email' => false,
        ]);
        $user->assignRole('beneficiary');

        return $user;
    }

    private function staff(?string $permission = null): User
    {
        $user = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
        ]);
        $user->assignRole('staff');
        if ($permission !== null) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    private function registration(TrainingProgram $program, User $user, RegistrationStatus $status): ProgramRegistration
    {
        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => $status,
        ]);
    }

    private function attendanceLink(TrainingProgram $program, string $name, ?string $opensAt): ProgramAttendanceLink
    {
        return ProgramAttendanceLink::query()->create([
            'training_program_id' => $program->id,
            'name' => $name,
            'opens_at' => $opensAt,
            'open_minutes' => 30,
        ]);
    }

    public function test_dispatching_the_same_registration_twice_queues_one_job(): void
    {
        Queue::fake();
        [, $registration, , $issuer] = $this->approvedRegistration();

        IssueProgramCertificateJob::dispatch($registration->id, $issuer->id);
        IssueProgramCertificateJob::dispatch($registration->id, $issuer->id);

        Queue::assertPushed(IssueProgramCertificateJob::class, 1);
    }

    public function test_the_job_refuses_when_the_background_file_is_missing(): void
    {
        Storage::fake('public');
        [$program, $registration, $beneficiary, $issuer] = $this->approvedRegistration();
        CertificateTemplate::query()->create([
            'training_program_id' => $program->id,
            'background_path' => 'certificate-templates/missing.png',
            'elements' => [],
        ]);
        $existing = Certificate::query()->create([
            'user_id' => $beneficiary->id,
            'certificateable_type' => TrainingProgram::class,
            'certificateable_id' => $program->id,
            'certificate_number' => 'CERT-NO-BACKGROUND',
            'verification_code' => bin2hex(random_bytes(16)),
            'issued_at' => now(),
            'file_path' => null,
        ]);

        try {
            (new IssueProgramCertificateJob($registration->id, $issuer->id))
                ->handle(app(ProgramCertificateIssuance::class));
            $this->fail('The job should refuse without a background file.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Program certificate background is missing.', $exception->getMessage());
        }

        $existing->refresh();
        $this->assertNull($existing->file_path);
        $this->assertNull($existing->emailed_at);
        $this->assertSame(1, Certificate::query()->count());
        $this->assertSame(0, EmailLog::query()->count());
        $this->assertSame(0, InboxNotification::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_program_issuance_keeps_the_portal_notice_inside_the_platform(): void
    {
        Storage::fake('public');
        Notification::fake();

        [$program, $registration, $beneficiary, $issuer] = $this->approvedRegistration();
        $this->saveBackground($program);
        $beneficiary->forceFill([
            'notify_email' => true,
            'notification_settings' => [
                'categories' => [
                    'account' => ['in_app' => true, 'email' => true],
                ],
            ],
        ])->save();

        (new IssueProgramCertificateJob($registration->id, $issuer->id))
            ->handle(app(ProgramCertificateIssuance::class));

        Notification::assertSentToTimes($beneficiary, CertificateReadyEmail::class, 1);
        Notification::assertNotSentTo($beneficiary, InboxNotificationEmail::class);
        $this->assertSame(1, InboxNotification::query()->where('type', InboxNotificationType::CertificateIssued)->count());
        $this->assertSame(0, EmailLog::query()->where('template_key', 'inbox.certificate_issued')->count());

        $path = LearningPath::query()->create([
            'title' => 'مسار ما زال يرسل بريد التنبيه',
            'slug' => 'path-still-emails-'.uniqid(),
            'status' => PathStatus::Draft,
        ]);
        app(CertificateService::class)->issue($beneficiary, $path, $issuer);

        $opportunity = VolunteerOpportunity::query()->create([
            'title' => 'تطوع ما زال يرسل بريد التنبيه',
            'slug' => 'volunteer-still-emails-'.uniqid(),
            'status' => OpportunityStatus::Published,
            'published_at' => now(),
        ]);
        app(CertificateService::class)->issue($beneficiary, $opportunity, $issuer);

        Notification::assertSentToTimes($beneficiary, InboxNotificationEmail::class, 2);
        Notification::assertSentToTimes($beneficiary, CertificateReadyEmail::class, 1);
    }

    private function saveBackground(TrainingProgram $program): void
    {
        $path = 'certificate-templates/'.$program->id.'/background.png';
        $image = imagecreatetruecolor(4, 2);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, 3, 1, $white);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        Storage::disk('public')->put($path, $png === false ? '' : $png);
        CertificateTemplate::query()->updateOrCreate(
            ['training_program_id' => $program->id],
            [
                'background_path' => $path,
                'elements' => [],
            ],
        );
    }
}
