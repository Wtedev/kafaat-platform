<?php

namespace Tests\Feature\Certificates;

use App\Enums\ProgramStatus;
use App\Models\CertificateTemplate;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class CertificateDesignerTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbacRoles();
    }

    public function test_a_staff_member_without_permission_cannot_open_or_change_the_designer(): void
    {
        $program = $this->program();
        $staff = $this->staff();

        $this->actingAsOtpVerified($staff)
            ->get(route('certificate-designer.edit', $program))
            ->assertForbidden();

        $this->actingAsOtpVerified($staff)
            ->postJson(route('certificate-designer.update', $program), $this->validPayload())
            ->assertForbidden();

        $this->actingAsOtpVerified($staff)
            ->postJson(route('certificate-designer.background', $program), [
                'background' => UploadedFile::fake()->image('bg.jpg'),
            ])
            ->assertForbidden();

        $this->actingAsOtpVerified($staff)
            ->get(route('certificate-designer.preview', $program))
            ->assertForbidden();

        $this->actingAsOtpVerified($staff)
            ->get(route('certificate-designer.preview-page', $program))
            ->assertForbidden();
    }

    public function test_a_beneficiary_cannot_use_the_designer(): void
    {
        $program = $this->program();
        $beneficiary = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
        ]);
        $beneficiary->assignRole('beneficiary');

        $this->actingAsOtpVerified($beneficiary)
            ->get(route('certificate-designer.edit', $program))
            ->assertForbidden();

        $this->actingAsOtpVerified($beneficiary)
            ->postJson(route('certificate-designer.update', $program), $this->validPayload())
            ->assertForbidden();

        $this->actingAsOtpVerified($beneficiary)
            ->postJson(route('certificate-designer.background', $program), [
                'background' => UploadedFile::fake()->image('bg.jpg'),
            ])
            ->assertForbidden();

        $this->actingAsOtpVerified($beneficiary)
            ->get(route('certificate-designer.preview', $program))
            ->assertForbidden();

        $this->actingAsOtpVerified($beneficiary)
            ->get(route('certificate-designer.preview-page', $program))
            ->assertForbidden();
    }

    public function test_either_certificate_permission_opens_the_designer(): void
    {
        $program = $this->program();

        $this->actingAsOtpVerified($this->staff('certificates.issue'))
            ->get(route('certificate-designer.edit', $program))
            ->assertOk()
            ->assertSee($program->title)
            ->assertSee('اسم المستفيد')
            ->assertSee('مقاس A4 أفقي')
            ->assertSee('Diodrum Arabic')
            ->assertSee('value="extralight"', false)
            ->assertSee('value="medium"', false)
            ->assertSee('value="semibold"', false)
            ->assertSee('value="heavy"', false)
            ->assertSee('id="certificate-designer-undo"', false)
            ->assertSee('تراجع')
            ->assertSee('إعادة')
            ->assertSee('id="certificate-designer-start"', false)
            ->assertSee('id="certificate-designer-end"', false)
            ->assertSee('يُملأ تاريخ البداية والنهاية تلقائياً من البرنامج')
            ->assertSee('?relation=certificates', false);

        $this->actingAsOtpVerified($this->staff('issue_certificates'))
            ->get(route('certificate-designer.edit', $program))
            ->assertOk();
    }

    public function test_saving_rejects_an_unknown_field_an_invalid_color_a_position_past_100_and_html(): void
    {
        $program = $this->program();
        $staff = $this->staff('certificates.issue');
        $text = $this->textElement();

        $this->actingAsOtpVerified($staff)
            ->postJson(route('certificate-designer.update', $program), [
                'hours' => 3,
                'elements' => ['not_a_field' => $text],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['elements']);

        $text['color'] = 'blue';
        $this->actingAsOtpVerified($staff)
            ->postJson(route('certificate-designer.update', $program), [
                'hours' => 3,
                'elements' => ['beneficiary_name' => $text],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['elements.beneficiary_name']);

        $text['color'] = '#1a1a1a';
        $text['x'] = 100.01;
        $this->actingAsOtpVerified($staff)
            ->postJson(route('certificate-designer.update', $program), [
                'hours' => 3,
                'elements' => ['beneficiary_name' => $text],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['elements.beneficiary_name']);

        $text['x'] = 50;
        $text['font'] = '<b>shamel</b>';
        $this->actingAsOtpVerified($staff)
            ->postJson(route('certificate-designer.update', $program), [
                'hours' => 3,
                'elements' => ['beneficiary_name' => $text],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['elements.beneficiary_name']);

        $this->assertNull($program->certificateTemplate);
    }

    public function test_a_valid_design_is_saved(): void
    {
        $program = $this->program();

        $this->actingAsOtpVerified($this->staff('certificates.issue'))
            ->postJson(route('certificate-designer.update', $program), $this->validPayload())
            ->assertOk()
            ->assertJsonPath('message', 'تم حفظ تصميم الشهادة.');

        $template = CertificateTemplate::query()->where('training_program_id', $program->id)->first();
        $this->assertNotNull($template);
        $this->assertEquals(12.5, (float) $template->hours);
        $this->assertEquals(50, $template->elements['beneficiary_name']['x']);
        $this->assertSame('shamel', $template->elements['beneficiary_name']['font']);
    }

    public function test_a_custom_boundary_date_is_saved_and_the_program_date_stays_automatic(): void
    {
        $program = $this->program();
        $staff = $this->staff('certificates.issue');
        $start = $this->textElement();
        $end = $this->textElement();
        $start['value'] = '2026-01-05';
        $end['value'] = '2026-10-12';

        $this->actingAsOtpVerified($staff)
            ->postJson(route('certificate-designer.update', $program), [
                'hours' => 3,
                'elements' => [
                    'start_date' => $start,
                    'end_date' => $end,
                ],
            ])
            ->assertOk();

        $elements = $program->fresh()->certificateTemplate->elements;
        $this->assertSame('2026-01-05', $elements['start_date']['value']);
        $this->assertArrayNotHasKey('value', $elements['end_date']);

        $start['value'] = '2026-02-31';
        $this->actingAsOtpVerified($staff)
            ->postJson(route('certificate-designer.update', $program), [
                'hours' => 3,
                'elements' => ['start_date' => $start],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['elements.start_date']);

        $name = $this->textElement();
        $name['value'] = '2026-01-05';
        $this->actingAsOtpVerified($staff)
            ->postJson(route('certificate-designer.update', $program), [
                'hours' => 3,
                'elements' => ['beneficiary_name' => $name],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['elements.beneficiary_name']);
    }

    public function test_a_file_that_is_not_an_image_is_rejected(): void
    {
        Storage::fake('public');
        $program = $this->program();

        $this->actingAsOtpVerified($this->staff('certificates.issue'))
            ->post(route('certificate-designer.background', $program), [
                'background' => UploadedFile::fake()->createWithContent('notes.png', 'not an image'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['background']);

        $this->assertSame(0, CertificateTemplate::query()->count());
    }

    public function test_replacing_the_background_removes_the_previous_file(): void
    {
        Storage::fake('public');
        $program = $this->program();
        $staff = $this->staff('certificates.issue');

        $this->actingAsOtpVerified($staff)
            ->post(route('certificate-designer.background', $program), [
                'background' => UploadedFile::fake()->image('first.jpg', 30, 20),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $first = $program->fresh()->certificateTemplate->background_path;
        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);

        $this->actingAsOtpVerified($staff)
            ->post(route('certificate-designer.background', $program), [
                'background' => UploadedFile::fake()->image('second.png', 30, 20),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $second = $program->fresh()->certificateTemplate->background_path;
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    private function program(): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => 'برنامج تصميم الشهادة',
            'slug' => 'certificate-designer-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-12',
        ]);
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

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'hours' => 12.5,
            'elements' => [
                'beneficiary_name' => $this->textElement(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function textElement(): array
    {
        return [
            'x' => 50,
            'y' => 40,
            'font' => 'shamel',
            'weight' => 'regular',
            'size' => 24,
            'color' => '#1a1a1a',
            'align' => 'center',
            'width' => 70,
        ];
    }
}
