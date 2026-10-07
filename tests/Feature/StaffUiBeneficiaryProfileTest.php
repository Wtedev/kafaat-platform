<?php

namespace Tests\Feature;

use App\Enums\IdentityType;
use App\Enums\ProfileGender;
use App\Enums\UserDocumentStatus;
use App\Enums\UserDocumentType;
use App\Models\AuditLog;
use App\Models\EntityNote;
use App\Models\User;
use App\Models\UserDocument;
use App\Notifications\StaffEmailChangedByAdminNotification;
use App\Services\Identity\IdentityNumberService;
use App\Services\Rbac\RbacCatalog;
use App\Support\Privacy\SensitiveContactMasker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiBeneficiaryProfileTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    private const IDENTITY = '1099889081';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        config([
            'staff_ui.maintenance' => false,
            'staff_ui.ready_modules' => ['users'],
        ]);
    }

    public function test_guest_and_trainees_cannot_open_a_profile(): void
    {
        $beneficiary = $this->beneficiary();

        $this->get(route('staff-ui.users.show', $beneficiary))->assertRedirect(route('login'));

        $this->actingAsOtpVerified($beneficiary)
            ->get(route('staff-ui.users.show', $beneficiary))
            ->assertForbidden();
    }

    public function test_staff_without_list_permission_is_forbidden(): void
    {
        $beneficiary = $this->beneficiary();

        $this->actingAsOtpVerified($this->staff())
            ->get(route('staff-ui.users.show', $beneficiary))
            ->assertForbidden();
    }

    public function test_profile_hides_contact_and_identity_without_those_permissions(): void
    {
        $beneficiary = $this->beneficiary();
        $viewer = $this->staff(['users.view']);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.users.show', $beneficiary))
            ->assertOk()
            ->assertSee($beneficiary->fullName())
            ->assertSee('البيانات الأساسية والتواصل')
            ->assertSee('الملف المهني')
            ->assertSee('التسجيلات')
            ->assertSee('الشهادات')
            ->assertSee('ملاحظات داخلية')
            ->assertSee(SensitiveContactMasker::maskEmail($beneficiary->email))
            ->assertDontSee($beneficiary->email)
            ->assertDontSee(self::IDENTITY)
            ->assertDontSee('تعديل البيانات')
            ->assertDontSee('تعطيل الحساب')
            ->assertDontSee('تحميل السيرة الذاتية')
            ->assertDontSee('إظهار رقم الهوية')
            ->assertSee(route('staff-ui.users.index'), false);
    }

    public function test_contact_and_masked_identity_follow_their_permissions(): void
    {
        $beneficiary = $this->beneficiary();
        $viewer = $this->staff([
            'beneficiaries.view_basic',
            'beneficiaries.view_contact',
            'beneficiaries.identity.view_masked',
        ]);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.users.show', $beneficiary))
            ->assertOk()
            ->assertSee($beneficiary->email)
            ->assertSee($beneficiary->phone)
            ->assertSee(IdentityNumberService::mask('9081'))
            ->assertDontSee(self::IDENTITY)
            ->assertDontSee('إظهار رقم الهوية');
    }

    public function test_basic_update_does_not_change_email(): void
    {
        $beneficiary = $this->beneficiary();
        $editor = $this->staff(['users.view', 'beneficiaries.update_basic']);

        $this->actingAsOtpVerified($editor)
            ->post(route('staff-ui.users.update', $beneficiary), [
                'basic' => '1',
                'first_name' => 'نورة',
                'father_name' => 'سعد',
                'grandfather_name' => 'محمد',
                'family_name' => 'القحطاني',
                'phone' => '0555000111',
                'city' => 'جدة',
                'email' => 'changed-by-basic@example.com',
            ])
            ->assertRedirect(route('staff-ui.users.show', $beneficiary));

        $beneficiary->refresh();
        $this->assertSame('نورة سعد محمد القحطاني', $beneficiary->fullName());
        $this->assertSame('+966555000111', $beneficiary->phone);
        $this->assertSame('جدة', $beneficiary->profile->city);
        $this->assertNotSame('changed-by-basic@example.com', $beneficiary->email);

        $audit = AuditLog::query()->where('action', 'beneficiary.updated')->where('target_user_id', $beneficiary->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame($editor->id, $audit->actor_id);
        $this->assertContains('phone', $audit->metadata['fields']);
        $this->assertContains('city', $audit->metadata['fields']);
        $this->assertStringNotContainsString('0555000111', (string) json_encode($audit->metadata, JSON_UNESCAPED_UNICODE));
        $this->assertNotContains('email', $audit->metadata['fields']);

        $this->actingAsOtpVerified($editor)
            ->post(route('staff-ui.users.update', $beneficiary), [
                'field' => 'city',
                'city' => 'الرياض',
            ])
            ->assertRedirect(route('staff-ui.users.show', $beneficiary));

        $beneficiary->refresh();
        $this->assertSame('الرياض', $beneficiary->profile->city);
        $this->assertSame('+966555000111', $beneficiary->phone);
        $this->assertSame('نورة سعد محمد القحطاني', $beneficiary->fullName());
    }

    public function test_sensitive_update_changes_only_email(): void
    {
        $beneficiary = $this->beneficiary();
        $editor = $this->staff(['users.view', 'beneficiaries.update_sensitive']);
        $originalName = $beneficiary->fullName();

        $this->actingAsOtpVerified($editor)
            ->post(route('staff-ui.users.update', $beneficiary), [
                'basic' => '1',
                'first_name' => 'اسم',
                'father_name' => 'آخر',
                'grandfather_name' => 'مختلف',
                'family_name' => 'تماما',
                'email' => 'Sensitive.New@example.com',
            ])
            ->assertRedirect(route('staff-ui.users.show', $beneficiary));

        $beneficiary->refresh();
        $this->assertSame($originalName, $beneficiary->fullName());
        $this->assertSame('sensitive.new@example.com', $beneficiary->email);
        $this->assertNull($beneficiary->email_verified_at);
    }

    public function test_email_change_notifies_the_old_address_and_ends_sessions_and_reset_tokens(): void
    {
        Notification::fake();
        $beneficiary = $this->beneficiary(['email' => 'old.beneficiary@example.com']);
        $editor = $this->staff(['users.view', 'beneficiaries.update_sensitive']);
        DB::table('sessions')->insert([
            'id' => 'beneficiary-session',
            'user_id' => $beneficiary->id,
            'payload' => 'test',
            'last_activity' => time(),
        ]);
        DB::table('password_reset_tokens')->insert([
            'email' => 'old.beneficiary@example.com',
            'token' => 'existing-token',
            'created_at' => now(),
        ]);
        $beneficiary->forceFill(['remember_token' => 'remember-me-cookie'])->save();

        $this->actingAsOtpVerified($editor)
            ->post(route('staff-ui.users.update', $beneficiary), [
                'field' => 'email',
                'email' => 'new.beneficiary@example.com',
            ])
            ->assertRedirect(route('staff-ui.users.show', $beneficiary));

        Notification::assertSentOnDemand(
            StaffEmailChangedByAdminNotification::class,
            function (StaffEmailChangedByAdminNotification $notification, array $channels, object $notifiable): bool {
                return $notifiable->routes['mail'] === 'old.beneficiary@example.com'
                    && in_array('إذا لم تكن تتوقع هذا التغيير، تواصل مع إدارة المنصة.', $notification->toMail($notifiable)->introLines, true);
            },
        );
        $this->assertDatabaseMissing('sessions', ['id' => 'beneficiary-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'old.beneficiary@example.com']);
        $fresh = $beneficiary->fresh();
        $this->assertSame('new.beneficiary@example.com', $fresh->email);
        $this->assertNotSame('remember-me-cookie', $fresh->remember_token);
        $this->assertNotNull($fresh->remember_token);
    }

    public function test_phone_uses_the_registration_saudi_mobile_rule(): void
    {
        $beneficiary = $this->beneficiary(['phone' => null]);
        $editor = $this->staff(['users.view', 'beneficiaries.update_basic']);

        $this->actingAsOtpVerified($editor)
            ->from(route('staff-ui.users.show', $beneficiary))
            ->post(route('staff-ui.users.update', $beneficiary), [
                'field' => 'phone',
                'phone' => '12345',
            ])
            ->assertRedirect(route('staff-ui.users.show', $beneficiary))
            ->assertSessionHasErrors('phone');

        $this->assertNull($beneficiary->fresh()->phone);

        $this->actingAsOtpVerified($editor)
            ->post(route('staff-ui.users.update', $beneficiary), [
                'field' => 'phone',
                'phone' => '0555000111',
            ])
            ->assertRedirect(route('staff-ui.users.show', $beneficiary))
            ->assertSessionHasNoErrors();

        $this->assertSame('+966555000111', $beneficiary->fresh()->phone);
    }

    public function test_notes_require_the_update_permission(): void
    {
        $beneficiary = $this->beneficiary();
        $viewer = $this->staff(['users.view']);
        $editor = $this->staff(['users.view', 'beneficiaries.update_basic']);

        EntityNote::query()->create([
            'noteable_type' => $beneficiary->getMorphClass(),
            'noteable_id' => $beneficiary->id,
            'created_by' => $editor->id,
            'body' => 'ملاحظة سابقة للفريق.',
        ]);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.users.show', $beneficiary))
            ->assertOk()
            ->assertSee('ملاحظة سابقة للفريق.')
            ->assertDontSee('إضافة ملاحظة');

        $this->actingAsOtpVerified($viewer)
            ->post(route('staff-ui.users.notes.store', $beneficiary), ['body' => 'لا يجب أن تُحفظ'])
            ->assertForbidden();

        $this->actingAsOtpVerified($editor)
            ->post(route('staff-ui.users.notes.store', $beneficiary), ['body' => 'ملاحظة جديدة من الموظف.'])
            ->assertRedirect(route('staff-ui.users.show', $beneficiary));

        $this->assertDatabaseHas('entity_notes', [
            'noteable_id' => $beneficiary->id,
            'body' => 'ملاحظة جديدة من الموظف.',
            'created_by' => $editor->id,
        ]);
    }

    public function test_deactivation_blocks_login_and_activation_restores_the_account(): void
    {
        $beneficiary = $this->beneficiary();
        $staff = $this->staff(['users.view', 'beneficiaries.deactivate']);

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.users.activation', $beneficiary), ['action' => 'deactivate'])
            ->assertRedirect(route('staff-ui.users.show', $beneficiary));

        $this->assertFalse($beneficiary->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.deactivated',
            'target_user_id' => $beneficiary->id,
        ]);

        auth()->logout();

        $this->post('/login', [
            'email' => $beneficiary->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.users.activation', $beneficiary), ['action' => 'activate'])
            ->assertRedirect(route('staff-ui.users.show', $beneficiary));

        $this->assertTrue($beneficiary->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.reactivated',
            'actor_id' => $staff->id,
            'target_user_id' => $beneficiary->id,
        ]);
    }

    public function test_cv_download_links_follow_the_existing_permission(): void
    {
        Storage::fake('private_documents');
        config(['cv.private_disk' => 'private_documents']);

        $beneficiary = $this->beneficiary();
        $path = 'cv/profile-test.pdf';
        Storage::disk('private_documents')->put($path, 'pdf');
        $document = UserDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $beneficiary->id,
            'document_type' => UserDocumentType::Cv,
            'disk' => 'private_documents',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 3,
            'sha256_checksum' => hash('sha256', 'pdf'),
            'status' => UserDocumentStatus::Active,
            'uploaded_by' => $beneficiary->id,
            'uploaded_at' => now(),
        ]);
        $beneficiary->profile->forceFill(['current_cv_document_id' => $document->id])->save();

        $viewer = $this->staff(['users.view']);
        $downloader = $this->staff(['users.view', 'beneficiary.cv.download']);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.users.show', $beneficiary))
            ->assertOk()
            ->assertDontSee(route('admin.beneficiaries.cv-file.download', $beneficiary), false);

        $this->actingAsOtpVerified($viewer)
            ->get(route('admin.beneficiaries.cv-file.download', $beneficiary))
            ->assertForbidden();

        $this->actingAsOtpVerified($downloader)
            ->get(route('staff-ui.users.show', $beneficiary))
            ->assertOk()
            ->assertSee(route('admin.beneficiaries.cv-pdf', $beneficiary), false)
            ->assertDontSee(route('admin.beneficiaries.cv-file.download', $beneficiary), false);

        $this->actingAsOtpVerified($downloader)
            ->get(route('admin.beneficiaries.cv-file.download', $beneficiary))
            ->assertOk();
    }

    public function test_identity_reveal_uses_the_existing_audit_path(): void
    {
        $beneficiary = $this->beneficiary();
        $denied = $this->staff(['users.view']);
        $allowed = $this->staff(['users.view', 'beneficiaries.identity.view_full']);

        $this->actingAsOtpVerified($allowed)
            ->get(route('staff-ui.users.show', $beneficiary))
            ->assertOk()
            ->assertSee(route('admin.beneficiaries.identity.reveal', $beneficiary), false)
            ->assertDontSee(self::IDENTITY);

        $this->actingAsOtpVerified($denied)
            ->postJson(route('admin.beneficiaries.identity.reveal', $beneficiary), [
                'password' => 'password',
                'reason' => 'مراجعة ملف المستفيد',
            ])
            ->assertForbidden();

        $this->actingAsOtpVerified($allowed)
            ->postJson(route('admin.beneficiaries.identity.reveal', $beneficiary), [
                'password' => 'password',
                'reason' => 'مراجعة ملف المستفيد',
            ])
            ->assertOk()
            ->assertJsonPath('identity_number', self::IDENTITY);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'identity.full_viewed',
            'actor_id' => $allowed->id,
            'target_user_id' => $beneficiary->id,
        ]);
    }

    public function test_staff_accounts_are_not_beneficiary_profiles(): void
    {
        $staff = $this->staff(['users.view']);

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.show', $staff))
            ->assertNotFound();
    }

    public function test_maintenance_keeps_the_profile_closed_for_staff(): void
    {
        config([
            'staff_ui.maintenance' => true,
            'staff_ui.ready_modules' => [],
        ]);
        $beneficiary = $this->beneficiary();
        $staff = $this->staff(['users.view']);

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.show', $beneficiary))
            ->assertForbidden();

        $this->assertNotContains('users', config('staff_ui.ready_modules'));
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
    private function beneficiary(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'name' => 'نورة سعد محمد القحطاني',
            'first_name' => 'نورة',
            'father_name' => 'سعد',
            'grandfather_name' => 'محمد',
            'family_name' => 'القحطاني',
            'email' => 'nora.profile@example.com',
            'phone' => '0555123490',
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $overrides));
        $user->assignRole(RbacCatalog::ROLE_BENEFICIARY);
        $user->forceFill(IdentityNumberService::prepareStoragePayload(self::IDENTITY, IdentityType::NationalId))->save();
        $user->profile()->create([
            'gender' => ProfileGender::Female,
            'birth_date' => '1995-04-12',
            'city' => 'الرياض',
            'job_title' => 'محللة بيانات',
            'bio' => 'نبذة قصيرة.',
            'cv_sections' => [
                'skills' => [[
                    'skill_name' => 'تحليل البيانات',
                    'level' => 'متقدم',
                    'category' => 'تقنية',
                ]],
                'education' => [[
                    'institution' => 'جامعة الملك سعود',
                    'degree_or_program' => 'بكالوريوس',
                    'field' => 'نظم المعلومات',
                    'start_year' => '2014',
                    'end_year' => '2018',
                    'is_current' => false,
                ]],
            ],
        ]);

        return $user->fresh(['profile']);
    }
}
