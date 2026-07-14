<?php

namespace Tests\Feature\Filament;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Filament\Resources\TrainingProgramResource\Pages\ViewTrainingProgram;
use App\Models\TrainingProgram;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class TrainingProgramDescriptionInlineEditTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    private const TIPTAP_JSON = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"تجربة","marks":[{"type":"bold"}]}]}]}';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_description_inline_edit_modal_mounts_with_tiptap_json(): void
    {
        $staff = $this->staffUser();
        $program = $this->createProgram([
            'description' => self::TIPTAP_JSON,
            'created_by' => $staff->id,
            'owner_id' => $staff->id,
        ]);

        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($staff)
            ->test(ViewTrainingProgram::class, ['record' => $program->getKey()])
            ->assertSuccessful()
            ->mountAction('editEntityField', ['field' => 'description'])
            ->assertActionMounted('editEntityField')
            ->assertFormFieldExists('description');
    }

    public function test_all_inline_edit_sections_mount_without_error(): void
    {
        foreach (self::inlineEditableSectionProvider() as $field) {
            $staff = $this->staffUser();
            $program = $this->createProgram([
                'description' => self::TIPTAP_JSON,
                'created_by' => $staff->id,
                'owner_id' => $staff->id,
            ]);

            $this->withSession(['otp_verified' => true]);

            Livewire::actingAs($staff)
                ->test(ViewTrainingProgram::class, ['record' => $program->getKey()])
                ->assertSuccessful()
                ->mountAction('editEntityField', ['field' => $field])
                ->assertActionMounted('editEntityField');
        }
    }

    /**
     * @return list<string>
     */
    private static function inlineEditableSectionProvider(): array
    {
        return ['overview', 'schedule', 'enrollment', 'team', 'description'];
    }

    public function test_inline_edit_support_has_no_removed_cover_section(): void
    {
        $this->assertSame(
            ['overview', 'schedule', 'enrollment', 'team', 'description'],
            \App\Filament\Support\TrainingProgramInlineEditSupport::fieldKeys(),
        );
    }

    public function test_description_inline_edit_save_persists_tiptap_json(): void
    {
        $staff = $this->staffUser();
        $program = $this->createProgram([
            'description' => self::TIPTAP_JSON,
            'created_by' => $staff->id,
            'owner_id' => $staff->id,
        ]);

        $updatedJson = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"نص محدّث","marks":[{"type":"bold"}]}]}]}';

        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($staff)
            ->test(ViewTrainingProgram::class, ['record' => $program->getKey()])
            ->assertSuccessful()
            ->mountAction('editEntityField', ['field' => 'description'])
            ->setActionData(['description' => $updatedJson])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('تم حفظ الإعدادات بنجاح')
            ->assertSee('نص محدّث');

        $program->refresh();

        $this->assertStringContainsString('"type":"doc"', (string) $program->description);
        $this->assertStringContainsString('bold', (string) $program->description);
        $this->assertNotSame(self::TIPTAP_JSON, $program->description);
    }

    public function test_description_inline_edit_save_with_invalid_schedule_calendar(): void
    {
        $staff = $this->staffUser();
        $program = $this->createProgram([
            'description' => self::TIPTAP_JSON,
            'created_by' => $staff->id,
            'owner_id' => $staff->id,
            'registration_end' => Carbon::parse('2026-09-01'),
            'end_date' => Carbon::parse('2026-08-15'),
        ]);

        $updatedJson = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"حفظ رغم جدول غير صالح","marks":[{"type":"bold"}]}]}]}';

        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($staff)
            ->test(ViewTrainingProgram::class, ['record' => $program->getKey()])
            ->assertSuccessful()
            ->mountAction('editEntityField', ['field' => 'description'])
            ->setActionData(['description' => $updatedJson])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('تم حفظ الإعدادات بنجاح')
            ->assertSee('حفظ رغم جدول غير صالح');

        $program->refresh();

        $this->assertStringContainsString('حفظ رغم جدول غير صالح', (string) $program->description);
    }

    public function test_description_inline_edit_save_with_deeply_nested_tiptap_document(): void
    {
        $staff = $this->staffUser();
        $deepJson = self::deeplyNestedTipTapJson();
        $program = $this->createProgram([
            'description' => $deepJson,
            'created_by' => $staff->id,
            'owner_id' => $staff->id,
        ]);

        $updatedJson = self::deeplyNestedTipTapJson('نص محدّث عميق');

        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($staff)
            ->test(ViewTrainingProgram::class, ['record' => $program->getKey()])
            ->assertSuccessful()
            ->mountAction('editEntityField', ['field' => 'description'])
            ->setActionData(['description' => $updatedJson])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('تم حفظ الإعدادات بنجاح')
            ->assertSee('نص محدّث عميق');

        $program->refresh();

        $this->assertStringContainsString('نص محدّث عميق', (string) $program->description);
    }

    public function test_view_panel_renders_description_as_html_not_raw_json(): void
    {
        $staff = $this->staffUser();
        $program = $this->createProgram([
            'description' => self::TIPTAP_JSON,
            'created_by' => $staff->id,
            'owner_id' => $staff->id,
        ]);

        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($staff)
            ->test(ViewTrainingProgram::class, ['record' => $program->getKey()])
            ->assertSuccessful()
            ->assertSee('تجربة')
            ->assertDontSee('"type":"doc"');
    }

    private static function deeplyNestedTipTapJson(string $text = 'فقرة'): string
    {
        $content = [];

        for ($index = 0; $index < 10; $index++) {
            $content[] = [
                'type' => 'bulletList',
                'content' => [[
                    'type' => 'listItem',
                    'content' => [[
                        'type' => 'paragraph',
                        'content' => [[
                            'type' => 'text',
                            'text' => "{$text} {$index}",
                            'marks' => [['type' => 'bold']],
                        ]],
                    ]],
                ]],
            ];
        }

        return json_encode([
            'type' => 'doc',
            'content' => $content,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function staffUser(): User
    {
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createProgram(array $overrides = []): TrainingProgram
    {
        $ownerId = $overrides['owner_id'] ?? $overrides['created_by'] ?? null;

        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج تجريبي',
            'slug' => 'inline-edit-test-'.uniqid(),
            'description' => 'وصف',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Self,
            'delivery_mode' => ProgramDeliveryMode::Remote,
            'status' => ProgramStatus::Draft,
            'start_date' => Carbon::parse('2026-08-01'),
            'end_date' => Carbon::parse('2026-08-15'),
            'registration_start' => Carbon::parse('2026-07-15'),
            'registration_end' => Carbon::parse('2026-08-14'),
            'capacity' => 30,
            'auto_accept_registrations' => true,
            'created_by' => $ownerId,
            'owner_id' => $ownerId,
        ], $overrides));
    }
}
