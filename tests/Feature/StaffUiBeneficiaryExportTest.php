<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\IdentityType;
use App\Enums\ProfileGender;
use App\Models\AuditLog;
use App\Models\InboxNotification;
use App\Models\User;
use App\Services\Identity\IdentityNumberService;
use App\Services\Rbac\RbacCatalog;
use App\Services\StaffUi\StaffBeneficiaryExport;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiBeneficiaryExportTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        config([
            'staff_ui.maintenance' => false,
            'staff_ui.ready_modules' => ['users'],
        ]);
    }

    public function test_export_button_downloads_for_authorized_staff_and_rejects_others(): void
    {
        $exporter = $this->staff(['beneficiaries.view_basic', 'exports.beneficiaries.basic']);
        $viewer = $this->staff(['beneficiaries.view_basic']);
        $listed = $this->beneficiaryWithProfile(['name' => 'مستفيد ظاهر في التصدير']);

        $this->actingAsOtpVerified($exporter)
            ->get(route('staff-ui.users.index'))
            ->assertOk()
            ->assertSee('تصدير Excel')
            ->assertSee('تصدير ملفات المستفيدين')
            ->assertSee('سيتم تصدير مستفيد واحد حسب الفلاتر الحالية')
            ->assertSee('الاسم الكامل')
            ->assertDontSee('البريد الإلكتروني')
            ->assertDontSee('رقم الجوال');

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.users.index'))
            ->assertOk()
            ->assertDontSee('تصدير Excel');

        $this->actingAsOtpVerified($viewer)
            ->post(route('staff-ui.users.export'), ['columns' => ['user_name']])
            ->assertForbidden();

        $response = $this->actingAsOtpVerified($exporter)
            ->post(route('staff-ui.users.export'), ['columns' => ['user_name']]);

        $response->assertOk();
        $response->assertDownload();
        $this->assertStringContainsString(now()->format('Y-m-d'), (string) $response->headers->get('content-disposition'));
        $this->assertContains('مستفيد ظاهر في التصدير', $this->sheetValues($response));
        $this->assertTrue($this->sheet($response)->getRightToLeft());
    }

    public function test_contact_columns_are_rejected_without_permission_even_when_posted(): void
    {
        $staff = $this->staff(['beneficiaries.view_basic', 'exports.beneficiaries.basic']);
        $listed = $this->beneficiaryWithProfile([
            'name' => 'مستفيد بلا تواصل',
            'email' => 'hidden-contact@example.com',
            'phone' => '0555000444',
        ]);

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.users.export'), [
                'columns' => ['user_name', 'user_email', 'user_phone'],
            ])
            ->assertSessionHasErrors('columns');

        $this->assertSame(0, AuditLog::query()->where('action', 'export.generated')->count());
        $this->assertNotNull($listed->id);
    }

    public function test_export_applies_the_current_page_filters(): void
    {
        $staff = $this->staff(['beneficiaries.view_basic', 'exports.beneficiaries.basic']);
        $this->beneficiaryWithProfile(['name' => 'نورة المكتملة', 'email' => 'complete-export@example.com']);
        $inactive = $this->beneficiaryWithProfile([
            'name' => 'ليان المعطلة',
            'email' => 'inactive-export@example.com',
            'is_active' => false,
        ]);
        $this->beneficiaryWithProfile([
            'name' => 'زيد الناقص',
            'email' => 'incomplete-export@example.com',
        ], complete: false);

        $filtered = $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.index', ['status' => 'inactive', 'q' => 'ليان']));

        $filtered->assertOk()->assertSee('سيتم تصدير مستفيد واحد حسب الفلاتر الحالية');

        $response = $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.users.export'), [
                'columns' => ['user_name'],
                'q' => 'ليان',
                'status' => 'inactive',
                'profile' => '',
            ]);

        $response->assertOk();
        $values = $this->sheetValues($response);
        $this->assertContains($inactive->name, $values);
        $this->assertNotContains('نورة المكتملة', $values);
        $this->assertNotContains('زيد الناقص', $values);
    }

    public function test_export_is_written_to_the_audit_log(): void
    {
        $staff = $this->staff(['beneficiaries.view_basic', 'exports.beneficiaries.basic']);
        $this->beneficiaryWithProfile(['name' => 'مستفيد للتدقيق']);

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.users.export'), [
                'columns' => ['user_name'],
                'q' => 'تدقيق',
                'status' => 'active',
                'profile' => 'complete',
            ])
            ->assertOk();

        $log = AuditLog::query()->where('action', 'export.generated')->first();
        $this->assertNotNull($log);
        $this->assertSame($staff->id, $log->actor_id);
        $this->assertNotNull($log->occurred_at);
        $this->assertSame(1, $log->metadata['row_count']);
        $this->assertSame(['user_name'], $log->metadata['selected_columns']);
        $this->assertSame([
            'q' => 'تدقيق',
            'status' => 'active',
            'profile' => 'complete',
        ], $log->metadata['filters']);
    }

    public function test_export_file_excludes_staff_admins_deleted_and_anonymized_accounts(): void
    {
        $staff = $this->staff(['beneficiaries.view_basic', 'exports.beneficiaries.basic'], [
            'name' => 'موظف لا يصدر',
        ]);
        $this->beneficiaryWithProfile(['name' => 'مستفيد داخل الملف']);
        $this->attachProfile($this->staff([], ['name' => 'موظف آخر لا يصدر']));
        $this->attachProfile($this->admin(['name' => 'مدير لا يصدر']));
        $deleted = $this->beneficiaryWithProfile(['name' => 'حساب محذوف لا يصدر']);
        $deleted->forceFill(['privacy_deleted_at' => now()])->save();
        $anonymized = $this->beneficiaryWithProfile(['name' => 'حساب مجهّل لا يصدر']);
        $anonymized->forceFill([
            'account_status' => AccountStatus::Anonymized,
            'anonymized_at' => now(),
        ])->save();
        $removed = $this->beneficiaryWithProfile(['name' => 'حساب غير تشغيلي لا يصدر']);
        $removed->forceFill(['account_status' => AccountStatus::Inactive])->save();

        $inside = User::query()->where('name', 'مستفيد داخل الملف')->firstOrFail();
        $inside->forceFill([
            'identity_type' => IdentityType::NationalId,
            'identity_number_ciphertext' => IdentityNumberService::encrypt('1099887766'),
            'identity_number_last4' => '7766',
            'identity_number_lookup_hash' => IdentityNumberService::generateLookupHash('1099887766'),
        ])->save();

        $response = $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.users.export'), ['columns' => ['user_name']]);

        $response->assertOk();
        $values = $this->sheetValues($response);
        $raw = $this->spreadsheetBytes($response);

        $this->assertContains('مستفيد داخل الملف', $values);
        $this->assertNotContains('موظف لا يصدر', $values);
        $this->assertNotContains('موظف آخر لا يصدر', $values);
        $this->assertNotContains('مدير لا يصدر', $values);
        $this->assertNotContains('حساب محذوف لا يصدر', $values);
        $this->assertNotContains('حساب مجهّل لا يصدر', $values);
        $this->assertNotContains('حساب غير تشغيلي لا يصدر', $values);
        $this->assertStringNotContainsString('1099887766', (string) $raw);
    }

    public function test_export_is_limited_to_ten_attempts_per_hour(): void
    {
        $staff = $this->staff(['beneficiaries.view_basic', 'exports.beneficiaries.basic']);
        $this->beneficiaryWithProfile(['name' => 'مستفيد للحد']);
        RateLimiter::clear(StaffBeneficiaryExport::rateLimitKey($staff));

        for ($attempt = 0; $attempt < StaffBeneficiaryExport::HOURLY_LIMIT; $attempt++) {
            RateLimiter::hit(StaffBeneficiaryExport::rateLimitKey($staff), 3600);
        }

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.users.export'), ['columns' => ['user_name']])
            ->assertStatus(429);

        $this->assertSame(0, AuditLog::query()->where('action', 'export.generated')->count());
    }

    public function test_large_exports_are_queued_with_a_signed_download_link(): void
    {
        Storage::fake('private_documents');
        Storage::fake('local');
        config(['staff_ui.beneficiary_export_sync_limit' => 1]);

        $staff = $this->staff([
            'beneficiaries.view_basic',
            'exports.beneficiaries.basic',
        ]);
        $other = $this->staff([
            'beneficiaries.view_basic',
            'exports.beneficiaries.basic',
        ]);
        $this->beneficiaryWithProfile(['name' => 'صف أول في الملف']);
        $this->beneficiaryWithProfile(['name' => 'صف ثان في الملف']);

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.users.export'), ['columns' => ['user_name']])
            ->assertRedirect()
            ->assertSessionHas('status');

        $notification = InboxNotification::query()->where('user_id', $staff->id)->first();
        $this->assertNotNull($notification);
        $url = $notification->context['download_url'] ?? null;
        $this->assertIsString($url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertEqualsWithDelta(now()->addHours(24)->getTimestamp(), (int) ($query['expires'] ?? 0), 120);

        $download = $this->actingAsOtpVerified($staff)->get($url);
        $download->assertOk();
        $values = $this->sheetValues($download);
        $this->assertContains('صف أول في الملف', $values);
        $this->assertContains('صف ثان في الملف', $values);

        $this->actingAsOtpVerified($other)->get($url)->assertForbidden();

        $stored = Storage::disk('private_documents')->allFiles(StaffBeneficiaryExport::DIRECTORY);
        $this->assertCount(1, $stored);
        $this->assertSame([], Storage::disk('local')->allFiles(StaffBeneficiaryExport::DIRECTORY));

        $log = AuditLog::query()->where('action', 'export.generated')->first();
        $this->assertNotNull($log);
        $this->assertSame(2, $log->metadata['row_count']);
        $this->assertSame($staff->id, $log->actor_id);
    }

    public function test_export_count_follows_arabic_number_rules(): void
    {
        $this->assertSame(
            'لا يوجد مستفيدون حسب الفلاتر الحالية.',
            StaffBeneficiaryExport::countSentence(0),
        );
        $this->assertSame(
            'سيتم تصدير مستفيد واحد حسب الفلاتر الحالية.',
            StaffBeneficiaryExport::countSentence(1),
        );
        $this->assertSame(
            'سيتم تصدير مستفيدان حسب الفلاتر الحالية.',
            StaffBeneficiaryExport::countSentence(2),
        );
        $this->assertSame(
            'سيتم تصدير 3 مستفيدين حسب الفلاتر الحالية.',
            StaffBeneficiaryExport::countSentence(3),
        );
        $this->assertSame(
            'سيتم تصدير 10 مستفيدين حسب الفلاتر الحالية.',
            StaffBeneficiaryExport::countSentence(10),
        );
        $this->assertSame(
            'سيتم تصدير 11 مستفيداً حسب الفلاتر الحالية.',
            StaffBeneficiaryExport::countSentence(11),
        );
        $this->assertSame(
            'سيتم تصدير 143 مستفيداً حسب الفلاتر الحالية.',
            StaffBeneficiaryExport::countSentence(143),
        );

        $staff = $this->staff(['beneficiaries.view_basic', 'exports.beneficiaries.basic']);
        $this->beneficiaryWithProfile(['name' => 'مستفيد أول']);
        $this->beneficiaryWithProfile(['name' => 'مستفيد ثان']);

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.index'))
            ->assertOk()
            ->assertSee('سيتم تصدير مستفيدان حسب الفلاتر الحالية');
    }

    public function test_failed_background_export_notifies_the_staff_member(): void
    {
        config([
            'staff_ui.beneficiary_export_sync_limit' => 0,
            'cv.private_disk' => 'missing-private-disk',
        ]);

        $staff = $this->staff(['beneficiaries.view_basic', 'exports.beneficiaries.basic']);
        $this->beneficiaryWithProfile(['name' => 'مستفيد لم يُصدَّر']);

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.users.export'), ['columns' => ['user_name']])
            ->assertStatus(500);

        $notification = InboxNotification::query()->where('user_id', $staff->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame('تعذر تصدير المستفيدين', $notification->title);
        $this->assertSame('تعذر إنشاء ملف التصدير. حاول مرة أخرى لاحقاً.', $notification->message);
        $this->assertArrayNotHasKey('download_url', $notification->context ?? []);
    }

    public function test_expired_queued_exports_are_deleted_after_twenty_four_hours(): void
    {
        Storage::fake('private_documents');
        config(['cv.private_disk' => 'private_documents']);

        $disk = Storage::disk('private_documents');
        $disk->put(StaffBeneficiaryExport::DIRECTORY.'/4/old.xlsx', 'old');
        $disk->put(StaffBeneficiaryExport::DIRECTORY.'/4/fresh.xlsx', 'fresh');
        touch(
            $disk->path(StaffBeneficiaryExport::DIRECTORY.'/4/old.xlsx'),
            now()->subHours(25)->getTimestamp(),
        );

        $this->artisan('staff-ui:purge-expired-beneficiary-exports', ['--dry-run' => true])
            ->assertSuccessful();
        $disk->assertExists(StaffBeneficiaryExport::DIRECTORY.'/4/old.xlsx');

        $this->artisan('staff-ui:purge-expired-beneficiary-exports')->assertSuccessful();

        $disk->assertMissing(StaffBeneficiaryExport::DIRECTORY.'/4/old.xlsx');
        $disk->assertExists(StaffBeneficiaryExport::DIRECTORY.'/4/fresh.xlsx');

        $scheduled = collect(app(Schedule::class)->events())
            ->contains(fn ($event): bool => str_contains((string) $event->command, 'staff-ui:purge-expired-beneficiary-exports')
                && $event->expression === '45 3 * * *');
        $this->assertTrue($scheduled);
    }

    /**
     * @return list<string>
     */
    private function sheetValues(TestResponse $response): array
    {
        $values = [];

        foreach ($this->sheet($response)->toArray() as $row) {
            foreach ($row as $cell) {
                if ($cell !== null && $cell !== '') {
                    $values[] = (string) $cell;
                }
            }
        }

        return $values;
    }

    private function sheet(TestResponse $response): Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $this->spreadsheetBytes($response));
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        return $sheet;
    }

    private function spreadsheetBytes(TestResponse $response): string
    {
        if ($response->baseResponse instanceof BinaryFileResponse) {
            return (string) file_get_contents($response->baseResponse->getFile()->getPathname());
        }

        return $response->streamedContent();
    }

    /**
     * @param  list<string>  $permissions
     * @param  array<string, mixed>  $overrides
     */
    private function staff(array $permissions = [], array $overrides = []): User
    {
        $staff = User::factory()->create(array_merge([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $overrides));
        $staff->assignRole(RbacCatalog::ROLE_STAFF);
        if ($permissions !== []) {
            $staff->givePermissionTo($permissions);
        }

        return $staff->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function admin(array $overrides = []): User
    {
        $admin = User::factory()->create(array_merge([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $overrides));
        $admin->assignRole(RbacCatalog::ROLE_ADMIN);

        return $admin->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function beneficiaryWithProfile(array $overrides = [], bool $complete = true): User
    {
        $user = User::factory()->create(array_merge([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
            'phone' => $complete ? '0555123490' : null,
        ], $overrides));
        $user->assignRole(RbacCatalog::ROLE_BENEFICIARY);

        if ($complete) {
            $user->forceFill([
                'first_name' => 'نورة',
                'father_name' => 'سعد',
                'grandfather_name' => 'محمد',
                'family_name' => 'القحطاني',
                'identity_type' => IdentityType::NationalId,
                'identity_number_last4' => '9081',
                'identity_number_lookup_hash' => hash('sha256', 'export-beneficiary-'.$user->id),
            ])->save();
        }

        $this->attachProfile($user, $complete);

        return $user->fresh(['profile']);
    }

    private function attachProfile(User $user, bool $complete = true): void
    {
        $user->profile()->create([
            'gender' => $complete ? ProfileGender::Female : null,
            'birth_date' => $complete ? '1995-04-12' : null,
            'city' => 'الرياض',
        ]);
    }
}
