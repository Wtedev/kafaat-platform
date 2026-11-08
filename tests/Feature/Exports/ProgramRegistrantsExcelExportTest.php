<?php

namespace Tests\Feature\Exports;

use App\Enums\AttendanceMarkSource;
use App\Enums\AttendanceStatus;
use App\Enums\AuditLogResult;
use App\Enums\IdentityCategory;
use App\Enums\IdentityType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SurveyType;
use App\Exports\ProgramRegistrationsExport;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Profile;
use App\Models\ProgramAttendance;
use App\Models\ProgramAttendanceLink;
use App\Models\ProgramAttendanceMark;
use App\Models\ProgramRegistration;
use App\Models\ProgramSurvey;
use App\Models\SurveyCompletion;
use App\Models\SurveyResponse;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Exports\ProgramRegistrationExportAuthorization;
use App\Services\Exports\ProgramRegistrationExportService;
use App\Services\Identity\IdentityNumberService;
use App\Support\Exports\ExcelFormulaInjectionGuard;
use App\Support\Exports\ProgramRegistrationExportColumns;
use App\Support\ProgramRegistrationQuestions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;
use ZipArchive;

class ProgramRegistrantsExcelExportTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
    }

    public function test_exports_training_permission_is_independent_of_registrations_view(): void
    {
        $program = $this->makeProgram();
        $staffViewOnly = $this->makeStaff(['registrations.view', 'programs.view']);
        $staffExport = $this->makeStaff(['exports.training', 'programs.view']);
        $program->forceFill(['owner_id' => $staffExport->id])->save();

        $this->assertFalse(ProgramRegistrationExportAuthorization::canExport($staffViewOnly, $program));
        $this->assertTrue(ProgramRegistrationExportAuthorization::canExport($staffExport, $program));
        $this->assertTrue(Gate::forUser($staffExport)->allows('exportRegistrants', $program));
        $this->assertFalse(Gate::forUser($staffViewOnly)->allows('exportRegistrants', $program));
    }

    public function test_admin_can_export_and_staff_without_permission_cannot(): void
    {
        $program = $this->makeProgram();
        $admin = $this->makeAdmin();
        $staff = $this->makeStaff(['registrations.view', 'programs.view']);
        $program->forceFill(['owner_id' => $staff->id])->save();

        $this->assertTrue(ProgramRegistrationExportAuthorization::canExport($admin, $program));
        $this->assertFalse(ProgramRegistrationExportAuthorization::canExport($staff, $program));
    }

    public function test_default_columns_match_required_set(): void
    {
        $admin = $this->makeAdmin();
        $defaults = ProgramRegistrationExportColumns::defaultKeys($admin);

        $this->assertSame(
            ['user_name', 'user_email', 'user_phone', 'is_saudi', 'status', 'registered_at'],
            $defaults,
        );
    }

    public function test_saudi_column_is_default_for_every_program(): void
    {
        $admin = $this->makeAdmin();
        $program = $this->makeProgram(['slug' => 'saudi-col-'.uniqid()]);

        $this->assertContains('is_saudi', ProgramRegistrationExportColumns::defaultKeys($admin, $program));
        $this->assertSame('سعودي', ProgramRegistrationExportColumns::optionLabels($admin, $program)['is_saudi']);

        $saudi = $this->makeBeneficiary(['email' => 'saudi-'.uniqid().'@ex.com']);
        $saudi->forceFill(['identity_category' => IdentityCategory::Saudi])->save();
        $resident = $this->makeBeneficiary(['email' => 'resident-'.uniqid().'@ex.com']);
        $resident->forceFill(['identity_category' => IdentityCategory::Resident])->save();
        $unknown = $this->makeBeneficiary(['email' => 'unknown-'.uniqid().'@ex.com']);
        $unknown->forceFill(['identity_category' => null])->save();

        $this->assertSame('نعم', ProgramRegistrationExportColumns::resolve(
            $this->register($program, $saudi, RegistrationStatus::Pending),
            'is_saudi',
            $admin,
        ));
        $this->assertSame('لا', ProgramRegistrationExportColumns::resolve(
            $this->register($program, $resident, RegistrationStatus::Pending),
            'is_saudi',
            $admin,
        ));
        $this->assertSame('غير محدد', ProgramRegistrationExportColumns::resolve(
            $this->register($program, $unknown, RegistrationStatus::Pending),
            'is_saudi',
            $admin,
        ));
    }

    public function test_column_allowlist_rejects_unknown_and_forbidden_keys(): void
    {
        $admin = $this->makeAdmin();
        $keys = ProgramRegistrationExportAuthorization::filterAllowedColumnKeys($admin, [
            'user_name',
            'password',
            'identity_number_ciphertext',
            'remember_token',
            'not_a_real_column',
            'status',
        ]);

        $this->assertSame(['user_name', 'status'], $keys);
        $this->assertNotContains('identity_full', ProgramRegistrationExportColumns::allowlistedKeys($admin));
    }

    public function test_query_is_scoped_to_program_only(): void
    {
        $programA = $this->makeProgram(['slug' => 'export-a-'.uniqid()]);
        $programB = $this->makeProgram(['slug' => 'export-b-'.uniqid()]);
        $userA = $this->makeBeneficiary(['email' => 'a-'.uniqid().'@example.com']);
        $userB = $this->makeBeneficiary(['email' => 'b-'.uniqid().'@example.com']);
        $this->register($programA, $userA, RegistrationStatus::Approved);
        $this->register($programB, $userB, RegistrationStatus::Approved);

        $service = app(ProgramRegistrationExportService::class);
        $rows = $service->loadRegistrations($programA, ProgramRegistrationExportService::SCOPE_ALL);

        $this->assertCount(1, $rows);
        $this->assertSame($programA->id, $rows->first()->training_program_id);
        $this->assertSame($userA->id, $rows->first()->user_id);
    }

    public function test_status_scopes_filter_correctly(): void
    {
        $program = $this->makeProgram();
        $approved = $this->register($program, $this->makeBeneficiary(['email' => 'ap-'.uniqid().'@ex.com']), RegistrationStatus::Approved);
        $pending = $this->register($program, $this->makeBeneficiary(['email' => 'pe-'.uniqid().'@ex.com']), RegistrationStatus::Pending);
        $rejected = $this->register($program, $this->makeBeneficiary(['email' => 're-'.uniqid().'@ex.com']), RegistrationStatus::Rejected);

        $service = app(ProgramRegistrationExportService::class);

        $this->assertEqualsCanonicalizing(
            [$approved->id],
            $service->loadRegistrations($program, ProgramRegistrationExportService::SCOPE_APPROVED)->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$pending->id],
            $service->loadRegistrations($program, ProgramRegistrationExportService::SCOPE_PENDING)->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$rejected->id],
            $service->loadRegistrations($program, ProgramRegistrationExportService::SCOPE_REJECTED)->pluck('id')->all(),
        );
    }

    public function test_attended_and_absent_scopes_use_attendance_records(): void
    {
        $program = $this->makeProgram();
        $attendedUser = $this->makeBeneficiary(['email' => 'att-'.uniqid().'@ex.com']);
        $absentUser = $this->makeBeneficiary(['email' => 'abs-'.uniqid().'@ex.com']);
        $attended = $this->register($program, $attendedUser, RegistrationStatus::Approved);
        $absent = $this->register($program, $absentUser, RegistrationStatus::Approved);

        ProgramAttendance::query()->create([
            'program_registration_id' => $attended->id,
            'training_date' => now()->toDateString(),
            'status' => AttendanceStatus::Present,
        ]);
        ProgramAttendance::query()->create([
            'program_registration_id' => $absent->id,
            'training_date' => now()->toDateString(),
            'status' => AttendanceStatus::Absent,
        ]);

        $service = app(ProgramRegistrationExportService::class);
        $this->assertEqualsCanonicalizing(
            [$attended->id],
            $service->loadRegistrations($program, ProgramRegistrationExportService::SCOPE_ATTENDED)->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$absent->id],
            $service->loadRegistrations($program, ProgramRegistrationExportService::SCOPE_ABSENT)->pluck('id')->all(),
        );
    }

    public function test_empty_export_returns_null_and_audits_failure_without_pii(): void
    {
        $admin = $this->makeAdmin();
        $program = $this->makeProgram();
        $this->actingAs($admin);

        $response = app(ProgramRegistrationExportService::class)->download(
            $admin,
            $program,
            ['user_name', 'status'],
            ProgramRegistrationExportService::SCOPE_ALL,
        );

        $this->assertNull($response);

        $log = AuditLog::query()->where('action', 'export.generated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(AuditLogResult::Failure, $log->result);
        $this->assertSame(0, $log->metadata['row_count'] ?? null);
        $this->assertSame(['user_name', 'status'], $log->metadata['selected_columns'] ?? null);
        $this->assertArrayNotHasKey('email', $log->metadata ?? []);
        $this->assertArrayNotHasKey('phone', $log->metadata ?? []);
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame($program->id, $log->metadata['training_program_id'] ?? null);
    }

    public function test_successful_export_produces_valid_xlsx_with_arabic_headers_and_audit(): void
    {
        $admin = $this->makeAdmin();
        $program = $this->makeProgram(['slug' => 'leaders-export', 'title' => 'قادة']);
        $beneficiary = $this->makeBeneficiary([
            'name' => 'أحمد التجريبي',
            'email' => 'ahmad-export@example.com',
            'phone' => '0501234567',
        ]);
        Profile::query()->create([
            'user_id' => $beneficiary->id,
            'city' => 'الرياض',
            'job_title' => 'متدرب',
        ]);
        $this->register($program, $beneficiary, RegistrationStatus::Approved);
        $this->actingAs($admin);

        Excel::fake();

        $keys = ['user_name', 'user_email', 'user_phone', 'status', 'registered_at'];
        $response = app(ProgramRegistrationExportService::class)->download(
            $admin,
            $program,
            $keys,
            ProgramRegistrationExportService::SCOPE_ALL,
        );

        $this->assertNotNull($response);
        Excel::assertDownloaded(
            'مسجلو-برنامج-leaders-export-'.now()->timezone(config('app.timezone'))->format('Y-m-d').'.xlsx',
            function (ProgramRegistrationsExport $export) use ($keys): bool {
                $this->assertSame(
                    ProgramRegistrationExportColumns::labelsForKeys($keys),
                    $export->headings(),
                );
                $this->assertTrue(in_array('الاسم الكامل', $export->headings(), true));
                $this->assertTrue(in_array('البريد الإلكتروني', $export->headings(), true));
                $rows = $export->collection();
                $this->assertCount(1, $rows);
                $this->assertSame('أحمد التجريبي', $rows->first()[0]);
                $this->assertSame('ahmad-export@example.com', $rows->first()[1]);
                $this->assertSame('0501234567', $rows->first()[2]);

                return true;
            },
        );

        $log = AuditLog::query()->where('action', 'export.generated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(AuditLogResult::Success, $log->result);
        $this->assertSame(1, $log->metadata['row_count'] ?? null);
        $this->assertSame($keys, $log->metadata['selected_columns'] ?? null);
        $serialized = json_encode($log->metadata);
        $this->assertStringNotContainsString('ahmad-export@example.com', (string) $serialized);
        $this->assertStringNotContainsString('0501234567', (string) $serialized);
    }

    public function test_real_xlsx_binary_is_zip_with_workbook_xml(): void
    {
        $admin = $this->makeAdmin();
        $program = $this->makeProgram();
        $this->register(
            $program,
            $this->makeBeneficiary(['email' => 'xlsx-'.uniqid().'@ex.com']),
            RegistrationStatus::Pending,
        );
        $this->actingAs($admin);

        $registrations = app(ProgramRegistrationExportService::class)->loadRegistrations(
            $program,
            ProgramRegistrationExportService::SCOPE_ALL,
        );
        $export = new ProgramRegistrationsExport($registrations, ['user_name', 'status'], $admin);
        $relative = 'testing-program-registrants-'.uniqid().'.xlsx';

        Excel::store($export, $relative, 'local');
        $stored = Storage::disk('local')->path($relative);
        $this->assertFileExists($stored);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($stored) === true);
        $this->assertNotFalse($zip->locateName('xl/workbook.xml'));
        $zip->close();

        $spreadsheet = IOFactory::load($stored);
        $sheet = $spreadsheet->getActiveSheet();
        $this->assertTrue($sheet->getRightToLeft());
        $this->assertSame('الاسم الكامل', $sheet->getCell('A1')->getValue());
        $this->assertNotEmpty($sheet->getAutoFilter()->getRange());

        @unlink($stored);
    }

    public function test_phone_and_formula_values_are_sanitized_as_text_safe(): void
    {
        $this->assertSame("'=1+1", ExcelFormulaInjectionGuard::sanitize('=1+1'));
        $this->assertSame("'+1234", ExcelFormulaInjectionGuard::sanitize('+1234'));
        $this->assertSame("'-1", ExcelFormulaInjectionGuard::sanitize('-1'));
        $this->assertSame("'@cmd", ExcelFormulaInjectionGuard::sanitize('@cmd'));
        $this->assertSame('0501234567', ExcelFormulaInjectionGuard::sanitize('0501234567'));

        $admin = $this->makeAdmin();
        $program = $this->makeProgram();
        $user = $this->makeBeneficiary([
            'name' => '=CMD()',
            'email' => 'formula-'.uniqid().'@ex.com',
            'phone' => '+966501234567',
        ]);
        $registration = $this->register($program, $user, RegistrationStatus::Approved);

        $name = ProgramRegistrationExportColumns::resolve($registration, 'user_name', $admin);
        $phone = ProgramRegistrationExportColumns::resolve($registration, 'user_phone', $admin);

        $this->assertSame("'=CMD()", $name);
        $this->assertSame("'+966501234567", $phone);
    }

    public function test_identity_export_is_masked_only_and_requires_identity_permission(): void
    {
        $admin = $this->makeAdmin();
        $staffNoIdentity = $this->makeStaff(['exports.training', 'programs.view']);
        $program = $this->makeProgram(['owner_id' => $staffNoIdentity->id]);

        $this->assertArrayHasKey('identity_masked', ProgramRegistrationExportColumns::optionLabels($admin));
        $this->assertArrayNotHasKey('identity_masked', ProgramRegistrationExportColumns::optionLabels($staffNoIdentity));

        $user = $this->makeBeneficiary(['email' => 'id-'.uniqid().'@ex.com']);
        $payload = IdentityNumberService::prepareStoragePayload('1234567890', IdentityType::NationalId);
        $user->forceFill($payload)->save();
        $registration = $this->register($program, $user, RegistrationStatus::Approved);

        $masked = ProgramRegistrationExportColumns::resolve($registration, 'identity_masked', $admin);
        $this->assertSame($user->maskedIdentityNumber(), $masked);
        $this->assertStringContainsString('7890', (string) $masked);
        $this->assertStringNotContainsString('1234567890', (string) $masked);
    }

    public function test_download_aborts_without_permission(): void
    {
        $program = $this->makeProgram();
        $staff = $this->makeStaff(['registrations.view', 'programs.view']);
        $program->forceFill(['owner_id' => $staff->id])->save();
        $this->actingAs($staff);

        $this->expectException(HttpException::class);

        app(ProgramRegistrationExportService::class)->download(
            $staff,
            $program,
            ['user_name'],
            ProgramRegistrationExportService::SCOPE_ALL,
        );
    }

    public function test_selected_scope_limits_to_ids(): void
    {
        $program = $this->makeProgram();
        $r1 = $this->register($program, $this->makeBeneficiary(['email' => 's1-'.uniqid().'@ex.com']), RegistrationStatus::Approved);
        $r2 = $this->register($program, $this->makeBeneficiary(['email' => 's2-'.uniqid().'@ex.com']), RegistrationStatus::Approved);

        $rows = app(ProgramRegistrationExportService::class)->loadRegistrations(
            $program,
            ProgramRegistrationExportService::SCOPE_SELECTED,
            null,
            [$r1->id],
        );

        $this->assertCount(1, $rows);
        $this->assertSame($r1->id, $rows->first()->id);
        $this->assertNotSame($r2->id, $rows->first()->id);
    }

    public function test_tatawaa_waei_export_includes_every_field_and_the_popup_answers(): void
    {
        $admin = $this->makeAdmin();
        $program = $this->makeProgram([
            'title' => 'تطوع واعي',
            'slug' => 'tatawaa-waei',
            'registration_questions' => ProgramRegistrationQuestions::forTatawaaWaei(),
        ]);
        $beneficiary = $this->makeBeneficiary([
            'name' => 'نورة التصدير',
            'email' => 'noura-export@example.com',
        ]);
        $registration = $this->register($program, $beneficiary, RegistrationStatus::Approved);
        $registration->forceFill([
            'registration_answers' => [
                'linkedin_url' => 'https://www.linkedin.com/in/noura',
                'major' => 'علوم حاسب',
                'content_experience' => 'لا توجد خبرة',
                'ai_knowledge' => 'أستخدمها بشكل محدود',
                'reason_to_join' => 'أريد صناعة محتوى.',
                'commitment_ready' => 'نعم، ملتزم تماماً',
            ],
        ])->save();

        $defaults = ProgramRegistrationExportColumns::defaultKeys($admin, $program);

        $this->assertSame(
            ProgramRegistrationExportColumns::allowlistedKeys($admin, $program),
            $defaults,
        );
        $this->assertContains('city', $defaults);
        $this->assertContains('answer_linkedin_url', $defaults);
        $this->assertContains('answer_reason_to_join', $defaults);

        $this->actingAs($admin);
        Excel::fake();

        $response = app(ProgramRegistrationExportService::class)->download(
            $admin,
            $program,
            ['user_name', 'status'],
            ProgramRegistrationExportService::SCOPE_ALL,
        );

        $this->assertNotNull($response);
        Excel::assertDownloaded(
            'مسجلو-برنامج-tatawaa-waei-'.now()->timezone(config('app.timezone'))->format('Y-m-d').'.xlsx',
            function (ProgramRegistrationsExport $export) use ($registration): bool {
                $headings = $export->headings();
                $this->assertContains('الاسم الكامل', $headings);
                $this->assertContains('رابط حسابك على منصة LinkedIn', $headings);
                $this->assertContains('لماذا ترغب في الانضمام لبرنامج «تطوع واعي»؟ وماذا تتوقع أن يضيف لهويتك المهنية؟', $headings);
                $this->assertNotContains('المدينة', $headings);

                $nameIndex = array_search('الاسم الكامل', $headings, true);
                $linkedinIndex = array_search('رابط حسابك على منصة LinkedIn', $headings, true);
                $majorIndex = array_search('التخصص الأكاديمي أو المهني الحالي', $headings, true);
                $row = $export->collection()->first();
                $this->assertSame('نورة التصدير', $row[$nameIndex]);
                $this->assertSame('https://www.linkedin.com/in/noura', $row[$linkedinIndex]);
                $this->assertSame('علوم حاسب', $row[$majorIndex]);
                $this->assertSame(
                    'https://www.linkedin.com/in/noura',
                    ProgramRegistrationExportColumns::resolve($registration, 'answer_linkedin_url', null),
                );

                return true;
            },
        );
    }

    public function test_no_password_or_token_columns_exist_in_catalog(): void
    {
        $keys = ProgramRegistrationExportColumns::allowlistedKeys($this->makeAdmin());
        foreach (['password', 'remember_token', 'identity_number_ciphertext', 'otp', 'token', 'deletion_request_id'] as $forbidden) {
            $this->assertNotContains($forbidden, $keys);
        }
    }

    public function test_progress_columns_cover_attendance_surveys_and_certificate(): void
    {
        $admin = $this->makeAdmin();
        $program = $this->makeProgram(['slug' => 'progress-export-'.uniqid()]);
        $beneficiary = $this->makeBeneficiary(['email' => 'progress-'.uniqid().'@ex.com', 'name' => 'مستفيد الحضور']);
        $registration = $this->register($program, $beneficiary, RegistrationStatus::Approved);

        $dayOne = ProgramAttendanceLink::query()->create([
            'training_program_id' => $program->id,
            'name' => 'اليوم الأول',
            'opens_at' => '2026-11-01 09:00:00',
            'closes_at' => '2026-11-01 10:00:00',
            'open_minutes' => 60,
        ]);
        $dayTwo = ProgramAttendanceLink::query()->create([
            'training_program_id' => $program->id,
            'name' => 'اليوم الثاني',
            'opens_at' => '2026-11-02 09:00:00',
            'closes_at' => '2026-11-02 10:00:00',
            'open_minutes' => 60,
        ]);
        ProgramAttendanceLink::query()->create([
            'training_program_id' => $program->id,
            'name' => 'يوم ملغى',
            'opens_at' => '2026-11-03 09:00:00',
            'closes_at' => '2026-11-03 10:00:00',
            'cancelled_at' => now(),
            'open_minutes' => 60,
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
        ProgramSurvey::query()->create([
            'training_program_id' => $program->id,
            'type' => SurveyType::Post,
            'is_anonymous' => false,
        ]);

        ProgramAttendanceMark::query()->create([
            'program_attendance_link_id' => $dayOne->id,
            'program_registration_id' => $registration->id,
            'attended_at' => now(),
            'source' => AttendanceMarkSource::Manual,
        ]);
        SurveyCompletion::query()->create([
            'program_survey_id' => $satisfaction->id,
            'registration_id' => $registration->id,
        ]);
        Certificate::query()->create([
            'user_id' => $beneficiary->id,
            'certificateable_type' => $program->getMorphClass(),
            'certificateable_id' => $program->id,
            'certificate_number' => 'CERT-EXPORT-1',
            'verification_code' => 'VERIFY-EXPORT-1',
            'file_path' => 'certificates/export-test.pdf',
            'issued_at' => now()->timezone(config('app.timezone'))->setDate(2026, 11, 4)->setTime(10, 0),
            'emailed_at' => now()->timezone(config('app.timezone'))->setDate(2026, 11, 4)->setTime(10, 15),
        ]);

        $labels = ProgramRegistrationExportColumns::optionLabels($admin, $program);
        $dayOneKey = 'attendance_day_'.$dayOne->id;
        $dayTwoKey = 'attendance_day_'.$dayTwo->id;

        $this->assertSame('حضور: اليوم الأول', $labels[$dayOneKey] ?? null);
        $this->assertSame('حضور: اليوم الثاني', $labels[$dayTwoKey] ?? null);
        $this->assertArrayNotHasKey(
            'attendance_day_'.ProgramAttendanceLink::query()->where('name', 'يوم ملغى')->value('id'),
            $labels,
        );
        $this->assertSame('استبيان قبلي', $labels['survey_pre'] ?? null);
        $this->assertSame('استبيان رضا', $labels['survey_satisfaction'] ?? null);
        $this->assertSame('استبيان بعدي', $labels['survey_post'] ?? null);
        $this->assertSame('الشهادة والإيميل', $labels['certificate_status'] ?? null);
        $this->assertSame(
            'الحضور والاستبيانات والشهادة',
            ProgramRegistrationExportColumns::groupTitles()[ProgramRegistrationExportColumns::GROUP_PROGRESS],
        );

        $keys = [
            $dayOneKey,
            $dayTwoKey,
            'survey_pre',
            'survey_satisfaction',
            'survey_post',
            'certificate_status',
        ];
        $this->assertSame(
            $keys,
            ProgramRegistrationExportAuthorization::filterAllowedColumnKeys($admin, $keys, $program),
        );

        $loaded = app(ProgramRegistrationExportService::class)
            ->loadRegistrations($program, ProgramRegistrationExportService::SCOPE_ALL)
            ->first();
        $this->assertNotNull($loaded);

        $this->assertSame('حاضر', ProgramRegistrationExportColumns::resolve($loaded, $dayOneKey, $admin, $program));
        $this->assertSame('لم يحضر', ProgramRegistrationExportColumns::resolve($loaded, $dayTwoKey, $admin, $program));
        $this->assertSame('لا', ProgramRegistrationExportColumns::resolve($loaded, 'survey_pre', $admin, $program));
        $this->assertSame('نعم', ProgramRegistrationExportColumns::resolve($loaded, 'survey_satisfaction', $admin, $program));
        $this->assertSame('لا', ProgramRegistrationExportColumns::resolve($loaded, 'survey_post', $admin, $program));
        $this->assertSame(
            'مصدرة — أُرسل إيميل بتاريخ 2026-11-04 10:15',
            ProgramRegistrationExportColumns::resolve($loaded, 'certificate_status', $admin, $program),
        );

        $withoutEmail = $this->makeBeneficiary(['email' => 'no-mail-'.uniqid().'@ex.com']);
        $regNoMail = $this->register($program, $withoutEmail, RegistrationStatus::Approved);
        Certificate::query()->create([
            'user_id' => $withoutEmail->id,
            'certificateable_type' => $program->getMorphClass(),
            'certificateable_id' => $program->id,
            'certificate_number' => 'CERT-EXPORT-2',
            'verification_code' => 'VERIFY-EXPORT-2',
            'file_path' => 'certificates/export-test-2.pdf',
            'issued_at' => now()->timezone(config('app.timezone'))->setDate(2026, 11, 5)->setTime(9, 0),
            'emailed_at' => null,
        ]);
        $loadedNoMail = app(ProgramRegistrationExportService::class)
            ->loadRegistrations($program, ProgramRegistrationExportService::SCOPE_SELECTED, null, [$regNoMail->id])
            ->first();
        $this->assertSame(
            'مصدرة بتاريخ 2026-11-05 09:00 — لم يُرسل إيميل',
            ProgramRegistrationExportColumns::resolve($loadedNoMail, 'certificate_status', $admin, $program),
        );

        SurveyResponse::query()->create([
            'program_survey_id' => ProgramSurvey::query()
                ->where('training_program_id', $program->id)
                ->where('type', SurveyType::Pre)
                ->value('id'),
            'registration_id' => $registration->id,
        ]);
        $reloaded = app(ProgramRegistrationExportService::class)
            ->loadRegistrations($program, ProgramRegistrationExportService::SCOPE_SELECTED, null, [$registration->id])
            ->first();
        $this->assertSame('نعم', ProgramRegistrationExportColumns::resolve($reloaded, 'survey_pre', $admin, $program));
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeStaff(array $permissions): User
    {
        $user = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('staff');

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
            $user->givePermissionTo($permission);
        }

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeBeneficiary(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $overrides));
        $user->assignRole('beneficiary');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProgram(array $overrides = []): TrainingProgram
    {
        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج تصدير',
            'slug' => 'export-program-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
            'registration_start' => now()->subDay()->toDateString(),
            'registration_end' => now()->addMonth()->toDateString(),
        ], $overrides));
    }

    private function register(
        TrainingProgram $program,
        User $user,
        RegistrationStatus $status,
    ): ProgramRegistration {
        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => $status,
            'approved_at' => $status === RegistrationStatus::Approved ? now() : null,
        ]);
    }
}
