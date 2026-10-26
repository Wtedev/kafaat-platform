<?php

namespace Tests\Feature\Certificates;

use App\Enums\PathStatus;
use App\Enums\ProgramStatus;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\LearningPath;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\CertificatePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class CertificateDesignPdfTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_preview_returns_a_pdf_and_does_not_create_a_certificate(): void
    {
        $program = $this->designedProgram();
        $staff = $this->staff();

        $response = $this->actingAsOtpVerified($staff)
            ->get(route('certificate-designer.preview', $program));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('FFShamelFamily', $response->getContent());

        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_render_sample_writes_a_pdf_without_creating_a_certificate(): void
    {
        $program = $this->designedProgram();

        $this->artisan('certificates:render-sample', ['program' => $program->id])
            ->assertOk();

        Storage::disk('local')->assertExists('certificate-samples/'.$program->id.'.pdf');
        $sample = Storage::disk('local')->get('certificate-samples/'.$program->id.'.pdf');
        $this->assertStringStartsWith('%PDF', $sample);
        $this->assertStringContainsString('FFShamelFamily', $sample);
        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_a_learning_path_certificate_still_uses_the_original_template(): void
    {
        $user = User::factory()->create([
            'name' => 'سارة المسار',
            'role_type' => 'beneficiary',
            'is_active' => true,
        ]);
        $path = LearningPath::query()->create([
            'title' => 'مسار الشهادة القديمة',
            'slug' => 'legacy-path-'.uniqid(),
            'status' => PathStatus::Draft,
        ]);
        $certificate = Certificate::query()->create([
            'user_id' => $user->id,
            'certificateable_type' => LearningPath::class,
            'certificateable_id' => $path->id,
            'certificate_number' => 'CERT-PATH-LEGACY',
            'verification_code' => bin2hex(random_bytes(16)),
            'issued_at' => now(),
        ]);

        $stored = app(CertificatePdfService::class)->generate($certificate);
        $bytes = Storage::disk('public')->get($stored);

        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertStringContainsString('IBMPlexSansArabic', $bytes);
        $this->assertStringNotContainsString('FFShamelFamily', $bytes);
    }

    public function test_a_program_without_a_background_keeps_the_original_template(): void
    {
        $user = User::factory()->create([
            'name' => 'نورة البرنامج',
            'role_type' => 'beneficiary',
            'is_active' => true,
        ]);
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج بلا تصميم',
            'slug' => 'legacy-program-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
        ]);
        $certificate = Certificate::query()->create([
            'user_id' => $user->id,
            'certificateable_type' => TrainingProgram::class,
            'certificateable_id' => $program->id,
            'certificate_number' => 'CERT-PROGRAM-LEGACY',
            'verification_code' => bin2hex(random_bytes(16)),
            'issued_at' => now(),
        ]);

        $stored = app(CertificatePdfService::class)->generate($certificate);

        $bytes = Storage::disk('public')->get($stored);
        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertStringContainsString('IBMPlexSansArabic', $bytes);
        $this->assertStringNotContainsString('FFShamelFamily', $bytes);
    }

    private function designedProgram(): TrainingProgram
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج المعاينة',
            'slug' => 'preview-program-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-12',
        ]);
        $background = UploadedFile::fake()->image('bg.jpg', 80, 50)
            ->store('certificate-templates/'.$program->id, 'public');
        CertificateTemplate::query()->create([
            'training_program_id' => $program->id,
            'background_path' => $background,
            'hours' => 6,
            'elements' => [
                'beneficiary_name' => [
                    'x' => 50,
                    'y' => 40,
                    'font' => 'shamel',
                    'weight' => 'bold',
                    'size' => 28,
                    'color' => '#1a1a1a',
                    'align' => 'center',
                    'width' => 70,
                ],
                'verification_qr' => [
                    'x' => 84,
                    'y' => 78,
                    'size' => 24,
                ],
            ],
        ]);

        return $program;
    }

    private function staff(): User
    {
        $user = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
        ]);
        $user->assignRole('staff');
        $user->givePermissionTo('certificates.issue');

        return $user;
    }
}
