<?php

namespace Tests\Feature\Exports;

use App\Enums\AttendanceStatus;
use App\Enums\AuditLogResult;
use App\Enums\IdentityType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Exports\ProgramRegistrationsExport;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\ProgramAttendance;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Exports\ProgramRegistrationExportAuthorization;
use App\Services\Exports\ProgramRegistrationExportService;
use App\Services\Identity\IdentityNumberService;
use App\Support\Exports\ExcelFormulaInjectionGuard;
use App\Support\Exports\ProgramRegistrationExportColumns;
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
            ['user_name', 'user_email', 'user_phone', 'status', 'registered_at'],
            $defaults,
        );
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

    public function test_no_password_or_token_columns_exist_in_catalog(): void
    {
        $keys = ProgramRegistrationExportColumns::allowlistedKeys($this->makeAdmin());
        foreach (['password', 'remember_token', 'identity_number_ciphertext', 'otp', 'token', 'deletion_request_id'] as $forbidden) {
            $this->assertNotContains($forbidden, $keys);
        }
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
