<?php

namespace Tests\Feature\PrivacyBaseline;

use App\Enums\ProgramStatus;
use App\Models\Certificate;
use App\Models\Profile;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class CertificateBaselineTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
    }

    public function test_owner_can_download_their_certificate(): void
    {
        Storage::fake('public');

        $owner = $this->makePortalUser(['email' => 'owner-cert@example.com']);
        $certificate = $this->makeCertificateForUser($owner);

        $this->actingAsOtpVerified($owner)
            ->get(route('certificates.download', $certificate))
            ->assertOk()
            ->assertDownload($certificate->certificate_number.'.pdf');
    }

    public function test_other_user_cannot_download_foreign_certificate(): void
    {
        Storage::fake('public');

        $owner = $this->makePortalUser(['email' => 'owner-cert@example.com']);
        $other = $this->makePortalUser(['email' => 'other-cert@example.com']);
        $certificate = $this->makeCertificateForUser($owner);

        $this->actingAsOtpVerified($other)
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    }

    public function test_public_verification_shows_current_fields_including_beneficiary_name(): void
    {
        $owner = $this->makePortalUser(['name' => 'سارة الشهادة', 'email' => 'cert-verify@example.com']);
        $certificate = $this->makeCertificateForUser($owner, persistFile: false);

        $this->get(route('certificates.verify', $certificate->verification_code))
            ->assertOk()
            ->assertSee('سارة الشهادة')
            ->assertSee($certificate->certificate_number)
            ->assertSee('شهادة صحيحة');
    }

    public function test_public_verification_returns_invalid_state_for_unknown_code(): void
    {
        $this->get(route('certificates.verify', 'UNKNOWN-CODE'))
            ->assertOk()
            ->assertSee('الشهادة غير صالحة');
    }

    private function makePortalUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $attributes));
        $user->assignRole('beneficiary');
        Profile::query()->create(['user_id' => $user->id]);

        return $user;
    }

    private function makeCertificateForUser(User $user, bool $persistFile = true): Certificate
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج الشهادة',
            'slug' => 'cert-program-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
        ]);

        $relativePath = 'certificates/baseline-'.uniqid().'.pdf';
        if ($persistFile) {
            Storage::disk('public')->put($relativePath, '%PDF-1.4 baseline');
        }

        return Certificate::query()->create([
            'user_id' => $user->id,
            'certificateable_type' => TrainingProgram::class,
            'certificateable_id' => $program->id,
            'certificate_number' => 'CERT-'.uniqid(),
            'verification_code' => 'VERIFY-'.uniqid(),
            'file_path' => $relativePath,
            'issued_at' => now(),
        ]);
    }
}
