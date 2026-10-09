<?php

namespace Tests\Feature;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Filament\Resources\TrainingProgramResource\Pages\ViewTrainingProgram;
use App\Filament\Support\TrainingEntityFormSupport;
use App\Mail\ProgramAcceptancePreviewMail;
use App\Models\AuditLog;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiProgramWizardTest extends TestCase
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
            'staff_ui.ready_modules' => ['training'],
        ]);
    }

    public function test_create_button_is_limited_to_staff_who_can_create_programs(): void
    {
        $viewer = $this->staff(['programs.view']);
        $creator = $this->staff(['programs.view', 'programs.create', 'programs.update']);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.index'))
            ->assertOk()
            ->assertDontSee('إضافة برنامج');

        $this->actingAsOtpVerified($creator)
            ->get(route('staff-ui.programs.index'))
            ->assertOk()
            ->assertSee('إضافة برنامج');

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.create'))
            ->assertForbidden();
    }

    public function test_each_step_rejects_invalid_input_and_keeps_the_draft(): void
    {
        $staff = $this->creator();

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.programs.wizard.store-new'), [
                'title' => '',
                'program_kind' => TrainingProgramKind::Course->value,
                'competency_track' => CompetencyTrack::Self->value,
                'delivery_mode' => ProgramDeliveryMode::InPerson->value,
            ])
            ->assertSessionHasErrors('title');

        $this->assertDatabaseCount('training_programs', 0);

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.programs.wizard.store-new'), $this->basics([
                'delivery_mode' => ProgramDeliveryMode::InPerson->value,
                'venue' => '',
            ]))
            ->assertSessionHasErrors('venue');

        $created = $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.programs.wizard.store-new'), $this->basics());
        $program = TrainingProgram::query()->firstOrFail();
        $created->assertRedirect(route('staff-ui.programs.wizard', [$program, 2]));

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.programs.wizard.store', [$program, 2]), [
                'start_date' => '2026-11-10',
                'end_date' => '2026-11-01',
                'registration_start' => '2026-11-12',
                'registration_end' => '2026-11-01',
            ])
            ->assertSessionHasErrors('start_date');

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.programs.wizard.store', [$program, 3]), [
                'capacity_mode' => 'shared',
                'capacity' => '',
            ])
            ->assertSessionHasErrors('capacity');

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.programs.wizard.store', [$program, 4]), [
                'whatsapp_groups_enabled' => '1',
                'whatsapp_group_male' => 'http://example.com/group',
                'whatsapp_group_female' => 'https://example.com/women',
            ])
            ->assertSessionHasErrors('whatsapp_group_male');

        $program->refresh();
        $this->assertSame(ProgramStatus::Draft, $program->status);
        $this->assertSame('برنامج المعالج', $program->title);
    }

    public function test_staff_can_save_a_draft_and_publish_a_program_that_opens_in_filament(): void
    {
        Mail::fake();
        $staff = $this->creator(publish: true);

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.programs.create'))
            ->assertOk()
            ->assertSee('البيانات الأساسية')
            ->assertSee('الغلاف');

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.programs.wizard.store-new'), $this->basics([
                'image' => UploadedFile::fake()->image('cover.jpg', 800, 600),
            ]))
            ->assertRedirect();

        $program = TrainingProgram::query()->firstOrFail();
        $this->assertNotNull($program->image);
        $this->assertSame($staff->id, $program->created_by);
        $this->assertSame($staff->id, $program->owner_id);
        $this->assertNull($program->learning_path_id);
        $this->assertTrue(AuditLog::query()->where('action', 'training_program.created')->where('resource_id', $program->id)->exists());

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.programs.wizard', [$program, 2]))
            ->assertOk()
            ->assertSee('تاريخ البداية');

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.programs.wizard.store', [$program, 2]), [
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-20',
                'registration_start' => '2026-10-01',
                'registration_end' => '2026-10-20',
                'weekdays' => [0, 2],
            ])
            ->assertRedirect(route('staff-ui.programs.wizard', [$program, 3]));

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.programs.wizard', [$program, 3]))
            ->assertOk()
            ->assertSee('سعوديون فقط')
            ->assertSee('كما ستظهر للمستفيد');

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.programs.wizard.store', [$program, 3]), [
                'acceptance_require_saudi_national' => '1',
                'acceptance_genders' => ['female'],
                'acceptance_min_age' => 18,
                'acceptance_max_age' => 40,
                'capacity_mode' => 'per_gender',
                'capacity_female' => 12,
                'auto_accept_registrations' => '1',
            ])
            ->assertRedirect(route('staff-ui.programs.wizard', [$program, 4]));

        $program->refresh();
        $this->assertTrue($program->acceptance_conditions['require_saudi_national']);
        $this->assertSame(['female'], $program->acceptance_conditions['genders']);
        $this->assertSame(12, $program->capacity_female);
        $this->assertNull($program->capacity);
        $this->assertTrue($program->auto_accept_registrations);

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.programs.wizard.preview-mail', $program), [
                'approval_message' => '<p>قبول تجريبي للمعالج</p>',
                'whatsapp_groups_enabled' => '1',
                'whatsapp_group_female' => 'https://example.com/women',
            ])
            ->assertRedirect(route('staff-ui.programs.wizard', [$program, 4]))
            ->assertSessionHas('status');

        Mail::assertSent(ProgramAcceptancePreviewMail::class, function (ProgramAcceptancePreviewMail $mail) use ($staff): bool {
            return $mail->hasTo($staff->email)
                && str_contains($mail->bodyHtml, 'قبول تجريبي للمعالج');
        });

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.programs.wizard.store', [$program, 5]), [
                'notify_audience' => '1',
            ])
            ->assertRedirect(route('staff-ui.programs.wizard', [$program, 6]));

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.programs.wizard', [$program, 6]))
            ->assertOk()
            ->assertSee('تعديل البيانات')
            ->assertSee('معاينة الصفحة العامة');

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.programs.wizard.preview', $program))
            ->assertOk()
            ->assertSee('برنامج المعالج');

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.programs.wizard.store', [$program, 6]), [
                'publish' => '0',
            ])
            ->assertRedirect(route('staff-ui.programs.show', $program));

        $program->refresh();
        $this->assertSame(ProgramStatus::Draft, $program->status);
        $this->assertNull($program->published_at);
        $this->assertTrue($program->notify_on_publish);
        $this->assertTrue($program->notify_milestones);
        $this->assertTrue($program->notify_registrants_on_update);

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.programs.wizard.store', [$program, 5]), [
                'publish' => '1',
                'notify_audience' => '1',
            ]);
        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.programs.wizard.store', [$program, 6]), [
                'publish' => '1',
            ])
            ->assertRedirect(route('staff-ui.programs.show', $program));

        $program->refresh();
        $this->assertSame(ProgramStatus::Published, $program->status);
        $this->assertNotNull($program->published_at);
        $this->assertTrue(AuditLog::query()->where('action', 'training_program.published')->where('resource_id', $program->id)->exists());

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->withSession(['otp_verified' => true]);
        Livewire::actingAs($staff)
            ->test(ViewTrainingProgram::class, ['record' => $program->getKey()])
            ->assertSuccessful();
    }

    public function test_schedule_rules_reject_an_inverted_range(): void
    {
        $errors = TrainingEntityFormSupport::validateProgramScheduleDates([
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-01',
            'registration_start' => '2026-11-08',
            'registration_end' => '2026-11-02',
        ]);

        $this->assertNotEmpty($errors);
        $this->assertTrue(collect($errors)->contains(fn (string $error): bool => str_contains($error, 'نهاية البرنامج')));
        $this->assertTrue(collect($errors)->contains(fn (string $error): bool => str_contains($error, 'نهاية التسجيل')));
    }

    public function test_session_kind_drops_end_date_and_weekdays(): void
    {
        $staff = $this->creator();
        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.programs.wizard.store-new'), $this->basics([
                'program_kind' => TrainingProgramKind::Session->value,
            ]));
        $program = TrainingProgram::query()->firstOrFail();

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.programs.wizard.store', [$program, 2]), [
                'start_date' => '2026-11-01',
                'end_date' => '2026-12-01',
                'weekdays' => [1],
            ])
            ->assertRedirect();

        $program->refresh();
        $this->assertNull($program->end_date);
        $this->assertNull($program->weekdays);
    }

    /**
     * @param  list<string>  $extra
     */
    private function creator(bool $publish = false): User
    {
        $permissions = ['programs.view', 'programs.create', 'programs.update'];
        if ($publish) {
            $permissions[] = 'programs.publish';
        }

        return $this->staff($permissions);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function staff(array $permissions): User
    {
        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole(RbacCatalog::ROLE_STAFF);
        $staff->givePermissionTo($permissions);

        return $staff->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function basics(array $overrides = []): array
    {
        return array_merge([
            'title' => 'برنامج المعالج',
            'program_kind' => TrainingProgramKind::Course->value,
            'competency_track' => CompetencyTrack::Professional->value,
            'delivery_mode' => ProgramDeliveryMode::Remote->value,
            'description' => 'نبذة المعالج',
            'program_presenters' => [
                ['name' => 'نورة سعد', 'role' => 'مدربة'],
            ],
            'session_topics_enabled' => '1',
            'session_topics' => [
                ['title' => 'الافتتاح', 'facilitators' => 'نورة'],
            ],
        ], $overrides);
    }
}
