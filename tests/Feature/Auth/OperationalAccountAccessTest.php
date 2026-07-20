<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\UserDocumentStatus;
use App\Enums\UserDocumentType;
use App\Models\Certificate;
use App\Models\Profile;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\UserDocument;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class OperationalAccountAccessTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        Storage::fake('public');
        Storage::fake('private_documents');
    }

    /**
     * @return array<string, array{0: AccountStatus, 1: bool}>
     */
    public static function blockedAccountStatesProvider(): array
    {
        return [
            'inactive_status' => [AccountStatus::Inactive, true],
            'deletion_processing' => [AccountStatus::DeletionProcessing, true],
            'anonymized' => [AccountStatus::Anonymized, false],
            'is_active_false' => [AccountStatus::Active, false],
        ];
    }

    #[DataProvider('blockedAccountStatesProvider')]
    public function test_blocked_states_cannot_access_portal(
        AccountStatus $status,
        bool $isActive,
    ): void {
        $user = $this->makeBeneficiary([
            'account_status' => $status,
            'is_active' => $isActive,
        ]);

        $this->actingAsOtpVerified($user)
            ->get(route('portal.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    #[DataProvider('blockedAccountStatesProvider')]
    public function test_blocked_states_cannot_access_admin(
        AccountStatus $status,
        bool $isActive,
    ): void {
        $user = $this->makeAdmin([
            'account_status' => $status,
            'is_active' => $isActive,
        ]);

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));

        $this->actingAsOtpVerified($user)
            ->get('/admin')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    #[DataProvider('blockedAccountStatesProvider')]
    public function test_blocked_states_cannot_download_certificate(
        AccountStatus $status,
        bool $isActive,
    ): void {
        $user = $this->makeBeneficiary([
            'account_status' => $status,
            'is_active' => $isActive,
        ]);
        $certificate = $this->makeCertificateForUser($user);

        $this->actingAsOtpVerified($user)
            ->get(route('certificates.download', $certificate))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    #[DataProvider('blockedAccountStatesProvider')]
    public function test_blocked_states_cannot_download_own_cv(
        AccountStatus $status,
        bool $isActive,
    ): void {
        $user = $this->makeBeneficiaryWithCv([
            'account_status' => $status,
            'is_active' => $isActive,
        ]);

        $this->actingAsOtpVerified($user)
            ->get(route('portal.competency.cv.download'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    #[DataProvider('blockedAccountStatesProvider')]
    public function test_blocked_admin_cannot_use_attendance_gate_as_operator(
        AccountStatus $status,
        bool $isActive,
    ): void {
        $admin = $this->makeAdmin([
            'account_status' => $status,
            'is_active' => $isActive,
        ]);
        $program = $this->makeInPersonProgram();

        $this->actingAsOtpVerified($admin)
            ->get(route('gate.scan', $program))
            ->assertRedirect(route('gate.login', $program));
    }

    public function test_deletion_pending_retains_operational_access(): void
    {
        $beneficiary = $this->makeBeneficiary([
            'account_status' => AccountStatus::DeletionPending,
            'is_active' => true,
        ]);
        $certificate = $this->makeCertificateForUser($beneficiary);

        $this->actingAsOtpVerified($beneficiary)
            ->get(route('portal.dashboard'))
            ->assertOk();

        $this->actingAsOtpVerified($beneficiary)
            ->get(route('certificates.download', $certificate))
            ->assertOk()
            ->assertDownload($certificate->certificate_number.'.pdf');

        $admin = $this->makeAdmin([
            'account_status' => AccountStatus::DeletionPending,
            'is_active' => true,
        ]);

        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_active_users_remain_unaffected(): void
    {
        $beneficiary = $this->makeBeneficiary([
            'account_status' => AccountStatus::Active,
            'is_active' => true,
        ]);
        $certificate = $this->makeCertificateForUser($beneficiary);
        $withCv = $this->makeBeneficiaryWithCv([
            'email' => 'cv-active@example.com',
            'account_status' => AccountStatus::Active,
            'is_active' => true,
        ]);
        $admin = $this->makeAdmin([
            'account_status' => AccountStatus::Active,
            'is_active' => true,
        ]);
        $program = $this->makeInPersonProgram();

        $this->actingAsOtpVerified($beneficiary)
            ->get(route('portal.dashboard'))
            ->assertOk();

        $this->actingAsOtpVerified($beneficiary)
            ->get(route('certificates.download', $certificate))
            ->assertOk();

        $this->actingAsOtpVerified($withCv)
            ->get(route('portal.competency.cv.download'))
            ->assertOk();

        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));

        $this->actingAsOtpVerified($admin)
            ->get(route('gate.scan', $program))
            ->assertOk();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeBeneficiary(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => Hash::make('SecretPass1!'),
            'account_status' => AccountStatus::Active,
        ], $attributes));
        $user->assignRole('beneficiary');
        Profile::query()->create(['user_id' => $user->id, 'birth_date' => '1995-01-01']);

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeBeneficiaryWithCv(array $attributes = []): User
    {
        $user = $this->makeBeneficiary($attributes);

        $path = 'cv/operational-'.uniqid().'.pdf';
        Storage::disk('private_documents')->put($path, '%PDF-1.4 operational');

        $document = UserDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'document_type' => UserDocumentType::Cv,
            'disk' => 'private_documents',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 24,
            'sha256_checksum' => hash('sha256', '%PDF-1.4 operational'),
            'status' => UserDocumentStatus::Active,
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
        ]);

        $user->profile?->update(['current_cv_document_id' => $document->id]);

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeAdmin(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => Hash::make('SecretPass1!'),
            'account_status' => AccountStatus::Active,
        ], $attributes));
        $user->assignRole('admin');

        return $user->fresh();
    }

    private function makeCertificateForUser(User $user): Certificate
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج شهادة',
            'slug' => 'cert-op-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
        ]);

        $relativePath = 'certificates/op-'.uniqid().'.pdf';
        Storage::disk('public')->put($relativePath, '%PDF-1.4 operational');

        return Certificate::query()->create([
            'user_id' => $user->id,
            'certificateable_type' => TrainingProgram::class,
            'certificateable_id' => $program->id,
            'certificate_number' => 'CERT-OP-'.uniqid(),
            'verification_code' => 'VERIFY-OP-'.uniqid(),
            'file_path' => $relativePath,
            'issued_at' => now(),
        ]);
    }

    private function makeInPersonProgram(): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => 'برنامج حضور',
            'slug' => 'gate-op-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'delivery_mode' => ProgramDeliveryMode::InPerson,
        ]);
    }
}
