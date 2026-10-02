<?php

namespace Tests\Feature\Filament;

use App\Data\Certificates\EligibilityRules;
use App\Enums\CertificateEligibilityMode;
use App\Enums\CertificateTemplateStatus;
use App\Enums\ProgramStatus;
use App\Filament\Resources\TrainingProgramResource\Pages\ManageCertificateDesign;
use App\Models\TrainingProgram;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class ManageCertificateDesignTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('local');
    }

    public function test_user_without_permission_receives_403(): void
    {
        $program = $this->program();
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(ManageCertificateDesign::class, ['record' => $program->id])
            ->assertForbidden();
    }

    public function test_save_stores_valid_elements_and_rejects_overflow(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $eligibility = EligibilityRules::legacyProgramAverage()->toArray();

        Livewire::actingAs($admin)
            ->test(ManageCertificateDesign::class, ['record' => $program->id])
            ->call('saveDraft', [$this->nameElement()], $eligibility)
            ->assertHasNoErrors();

        $template = $program->certificateTemplate()->firstOrFail();
        $this->assertCount(1, $template->elements);
        $this->assertSame('recipient_name', $template->elements[0]->key?->value);
        $this->assertSame(CertificateTemplateStatus::Draft, $template->status);

        $overflow = $this->nameElement(['x' => 90, 'width' => 20]);

        Livewire::actingAs($admin)
            ->test(ManageCertificateDesign::class, ['record' => $program->id])
            ->call('saveDraft', [$overflow], $eligibility)
            ->assertHasErrors(['width']);

        $template->refresh();
        $this->assertSame(10.0, $template->elements[0]->x);
    }

    public function test_svg_upload_is_rejected(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $file = UploadedFile::fake()->createWithContent(
            'mark.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>',
        );

        Livewire::actingAs($admin)
            ->test(ManageCertificateDesign::class, ['record' => $program->id])
            ->set('backgroundUpload', $file)
            ->assertHasErrors(['backgroundUpload']);

        $this->assertNull($program->certificateTemplate()->firstOrFail()->background_path);
    }

    public function test_disguised_png_is_rejected(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $file = UploadedFile::fake()->create('evil.png', 20, 'text/plain');

        Livewire::actingAs($admin)
            ->test(ManageCertificateDesign::class, ['record' => $program->id])
            ->set('backgroundUpload', $file)
            ->assertHasErrors(['backgroundUpload']);

        $template = $program->certificateTemplate()->firstOrFail();
        $this->assertNull($template->background_path);
    }

    public function test_approval_fails_without_background_or_recipient_name(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $eligibility = EligibilityRules::legacyProgramAverage()->toArray();

        $component = Livewire::actingAs($admin)
            ->test(ManageCertificateDesign::class, ['record' => $program->id]);

        $component->call('approve', [$this->nameElement()], $eligibility);
        $this->assertNotEmpty($component->get('approvalErrors'));
        $this->assertSame(1, $program->certificateTemplate()->firstOrFail()->version);

        $template = $program->certificateTemplate()->firstOrFail();
        Storage::disk('local')->put('certificate-backgrounds/sample.png', $this->pngBytes());
        $template->update([
            'background_path' => 'certificate-backgrounds/sample.png',
            'background_disk' => 'local',
        ]);

        $again = Livewire::actingAs($admin)
            ->test(ManageCertificateDesign::class, ['record' => $program->id]);
        $again->call('approve', [], $eligibility);
        $this->assertNotEmpty($again->get('approvalErrors'));
        $this->assertSame(1, $template->refresh()->version);
        $this->assertSame(CertificateTemplateStatus::Draft, $template->status);
    }

    public function test_approval_increments_version(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        Storage::disk('local')->put('certificate-backgrounds/sample.png', $this->pngBytes());

        $component = Livewire::actingAs($admin)
            ->test(ManageCertificateDesign::class, ['record' => $program->id]);

        $program->certificateTemplate()->firstOrFail()->update([
            'background_path' => 'certificate-backgrounds/sample.png',
            'background_disk' => 'local',
        ]);

        $component->call('approve', [$this->nameElement()], EligibilityRules::legacyProgramAverage()->toArray());

        $template = $program->certificateTemplate()->firstOrFail();
        $this->assertSame(2, $template->version);
        $this->assertSame(CertificateTemplateStatus::Ready, $template->status);
        $this->assertSame([], $component->get('approvalErrors'));
    }

    public function test_copy_does_not_copy_eligibility_rules(): void
    {
        $admin = $this->admin();
        $source = $this->program();
        $target = $this->program();

        $source->certificateTemplate()->create([
            'background_disk' => 'local',
            'page_width_mm' => 297,
            'page_height_mm' => 210,
            'elements' => [$this->nameElement(['key' => 'activity_title'])],
            'eligibility' => EligibilityRules::fromArray([
                'mode' => CertificateEligibilityMode::ScoreOnly->value,
                'min_score' => 40,
            ]),
            'status' => CertificateTemplateStatus::Ready,
            'version' => 3,
        ]);

        $component = Livewire::actingAs($admin)
            ->test(ManageCertificateDesign::class, ['record' => $target->id]);

        $component->call('copyFromProgram', $source->id);

        $template = $target->certificateTemplate()->firstOrFail();
        $this->assertSame(CertificateEligibilityMode::Average, $template->eligibility->mode);
        $this->assertSame(75.0, $template->eligibility->minAverage);
        $this->assertNull($template->eligibility->minScore);
        $this->assertCount(1, $template->elements);
        $this->assertSame('activity_title', $template->elements[0]->key?->value);
        $this->assertSame(CertificateTemplateStatus::Draft, $template->status);
    }

    public function test_background_page_size_follows_image_ratio_and_file_is_reencoded(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $image = imagecreatetruecolor(300, 100);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $file = UploadedFile::fake()->createWithContent('wide.png', $bytes === false ? '' : $bytes);

        Livewire::actingAs($admin)
            ->test(ManageCertificateDesign::class, ['record' => $program->id])
            ->set('backgroundUpload', $file)
            ->assertHasNoErrors();

        $template = $program->certificateTemplate()->firstOrFail();
        $this->assertEquals(297.0, (float) $template->page_width_mm);
        $this->assertEquals(99.0, (float) $template->page_height_mm);
        $this->assertNotNull($template->background_path);
        $stored = Storage::disk('local')->get($template->background_path);
        $this->assertIsString($stored);
        $this->assertSame('image/png', (new \finfo(FILEINFO_MIME_TYPE))->buffer($stored));
    }

    public function test_preview_returns_a_pdf(): void
    {
        $admin = $this->admin();
        $program = $this->program();

        $component = Livewire::actingAs($admin)
            ->test(ManageCertificateDesign::class, ['record' => $program->id]);

        $component->call('preview', [$this->nameElement()], null);

        $url = $component->get('previewUrl');
        $this->assertIsString($url);

        $response = $this->actingAs($admin)->get($url);
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
        ]);
    }

    private function program(): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => 'برنامج التصميم '.Str::lower(Str::random(4)),
            'slug' => 'cert-design-'.Str::lower(Str::random(8)),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function nameElement(array $overrides = []): array
    {
        return array_merge([
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
        ], $overrides);
    }

    private function pngBytes(): string
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

        return $bytes === false ? '' : $bytes;
    }
}
