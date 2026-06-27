<?php

namespace Tests\Feature\Security;

use App\Enums\ProgramStatus;
use App\Models\Certificate;
use App\Models\Profile;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateVerificationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_certificate_does_not_leak_user_existence_details(): void
    {
        $response = $this->get(route('certificates.verify', ['code' => 'NON-EXISTENT-CODE']));

        $response->assertOk();
        $response->assertSee('غير صالحة', false);
        $response->assertDontSee('@example.com', false);
    }

    public function test_valid_certificate_shows_minimal_fields_only(): void
    {
        $user = User::factory()->create([
            'name' => 'مستفيد اختبار',
            'email' => 'cert-user@example.com',
        ]);
        Profile::query()->create(['user_id' => $user->id, 'birth_date' => '1995-01-01']);

        $program = TrainingProgram::query()->create([
            'title' => 'برنامج اختبار',
            'slug' => 'cert-program-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
        ]);

        $certificate = Certificate::query()->create([
            'user_id' => $user->id,
            'certificateable_type' => TrainingProgram::class,
            'certificateable_id' => $program->id,
            'certificate_number' => 'CERT-TEST-001',
            'verification_code' => 'VERIFY-TEST-001',
            'issued_at' => now(),
        ]);

        $response = $this->get(route('certificates.verify', ['code' => $certificate->verification_code]));

        $response->assertOk();
        $response->assertSee($certificate->certificate_number, false);
        $response->assertDontSee('cert-user@example.com', false);
    }
}
