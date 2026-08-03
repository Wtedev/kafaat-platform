<?php

namespace Tests\Feature\Filament;

use App\Enums\AttendanceStatus;
use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramPrepDayType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Filament\Resources\TrainingProgramResource;
use App\Filament\Resources\TrainingProgramResource\Pages\ViewTrainingProgram;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramAttendanceRegistrationsRelationManager;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramPrepDaysRelationManager;
use App\Models\ProgramPrepDay;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramAttendanceService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class ProgramDailyAttendanceFilamentTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        config(['app.timezone' => 'Asia/Riyadh']);
        Carbon::setTestNow(Carbon::parse('2026-08-03 12:00:00', 'Asia/Riyadh'));
    }

    public function test_attendance_and_prep_days_relation_managers_registered_and_gated(): void
    {
        $admin = $this->admin();
        $program = $this->program($admin);

        $this->actingAs($admin);

        $this->assertContains(
            ProgramAttendanceRegistrationsRelationManager::class,
            TrainingProgramResource::getRelations(),
        );
        $this->assertContains(
            ProgramPrepDaysRelationManager::class,
            TrainingProgramResource::getRelations(),
        );
        $this->assertTrue(
            ProgramAttendanceRegistrationsRelationManager::canViewForRecord($program, ViewTrainingProgram::class),
        );
        $this->assertSame('أيام البرنامج', (new \ReflectionClass(ProgramPrepDaysRelationManager::class))->getStaticPropertyValue('title'));

        $outsider = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $outsider->assignRole('staff');
        $outsider->givePermissionTo(['programs.view']);

        $this->actingAs($outsider);
        $this->assertFalse(
            ProgramAttendanceRegistrationsRelationManager::canViewForRecord($program, ViewTrainingProgram::class),
        );
    }

    public function test_daily_toggle_marks_present_and_unmarks(): void
    {
        $admin = $this->admin();
        $program = $this->program($admin);
        $this->addPrepDay($program, '2026-08-03');
        $this->addPrepDay($program, '2026-08-04');
        $registration = $this->register($program, 'مستفيدة أ');

        $this->withSession(['otp_verified' => true]);
        $this->actingAs($admin);

        Livewire::actingAs($admin)
            ->test(ProgramAttendanceRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->set('selectedPrepDate', '2026-08-03')
            ->set('attendanceMode', 'daily')
            ->assertCanSeeTableRecords([$registration])
            ->assertDontSee('تغيير سريع')
            ->assertDontSee('اعتماد غياب')
            ->assertDontSee('غير محدد');

        app(ProgramAttendanceService::class)->setPresentState($registration, '2026-08-03', true, $admin);

        $this->assertDatabaseHas('program_attendance', [
            'program_registration_id' => $registration->id,
            'status' => AttendanceStatus::Present->value,
        ]);

        Livewire::actingAs($admin)
            ->test(ProgramAttendanceRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->set('selectedPrepDate', '2026-08-03')
            ->assertSee('حاضر');

        app(ProgramAttendanceService::class)->setPresentState($registration, '2026-08-03', false, $admin);
        $this->assertDatabaseMissing('program_attendance', [
            'program_registration_id' => $registration->id,
        ]);
    }

    public function test_matrix_mode_is_view_only_and_shows_binary_labels(): void
    {
        $admin = $this->admin();
        $program = $this->program($admin);
        $this->addPrepDay($program, '2026-08-03');
        $a = $this->register($program, 'أ');
        $b = $this->register($program, 'ب');

        app(ProgramAttendanceService::class)->markPresent($a, '2026-08-03', $admin);

        $this->withSession(['otp_verified' => true]);
        $this->actingAs($admin);

        Livewire::actingAs($admin)
            ->test(ProgramAttendanceRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->call('setAttendanceMode', 'matrix')
            ->assertSet('attendanceMode', 'matrix')
            ->assertSee('حاضر')
            ->assertSee('لم يحضر')
            ->assertDontSee('تحضير يدوي')
            ->assertDontSee('دليل الحالات')
            ->assertCanSeeTableRecords([$a, $b]);
    }

    public function test_prep_day_create_forces_requires_attendance_and_hides_toggle(): void
    {
        $admin = $this->admin();
        $program = $this->program($admin);

        $this->withSession(['otp_verified' => true]);
        $this->actingAs($admin);

        Livewire::actingAs($admin)
            ->test(ProgramPrepDaysRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->assertDontSee('يتطلب تحضير')
            ->callAction(TestAction::make('create')->table(), [
                'prep_date' => '2026-08-05',
                'delivery_type' => ProgramPrepDayType::Remote->value,
            ]);

        $day = ProgramPrepDay::query()
            ->where('training_program_id', $program->id)
            ->whereDate('prep_date', '2026-08-05')
            ->first();

        $this->assertNotNull($day);
        $this->assertTrue($day->requires_attendance);
        $this->assertSame(ProgramPrepDayType::Remote, $day->delivery_type);
    }

    public function test_qr_and_open_prep_visibility_only_for_selected_today(): void
    {
        $admin = $this->admin();
        $program = $this->program($admin);
        $this->addPrepDay($program, '2026-08-03', ProgramPrepDayType::InPerson);
        $this->addPrepDay($program, '2026-08-10', ProgramPrepDayType::Remote);

        $this->withSession(['otp_verified' => true]);
        $this->actingAs($admin);

        Livewire::actingAs($admin)
            ->test(ProgramAttendanceRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->set('selectedPrepDate', '2026-08-03')
            ->assertActionVisible(TestAction::make('openGateScan')->table())
            ->assertActionHidden(TestAction::make('startLiveSession')->table());

        Livewire::actingAs($admin)
            ->test(ProgramAttendanceRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->set('selectedPrepDate', '2026-08-10')
            ->assertActionHidden(TestAction::make('openGateScan')->table())
            ->assertActionHidden(TestAction::make('startLiveSession')->table()); // not TODAY

        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'Asia/Riyadh'));

        Livewire::actingAs($admin)
            ->test(ProgramAttendanceRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->set('selectedPrepDate', '2026-08-10')
            ->assertActionVisible(TestAction::make('startLiveSession')->table())
            ->assertActionHidden(TestAction::make('openGateScan')->table());
    }

    private function admin(): User
    {
        $admin = User::factory()->create([
            'role_type' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    private function program(User $owner): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => 'برنامج تحضير Filament',
            'slug' => 'filament-prep-'.uniqid(),
            'description' => 'وصف',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Self,
            'delivery_mode' => ProgramDeliveryMode::Hybrid,
            'venue' => 'القاعة',
            'status' => ProgramStatus::Published,
            'published_at' => now()->subDay(),
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'capacity' => 30,
            'auto_accept_registrations' => true,
        ]);
    }

    private function addPrepDay(
        TrainingProgram $program,
        string $date,
        ProgramPrepDayType $type = ProgramPrepDayType::InPerson,
    ): void {
        ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => $date,
            'delivery_type' => $type,
            'requires_attendance' => true,
        ]);
    }

    private function register(TrainingProgram $program, string $name): ProgramRegistration
    {
        $user = User::factory()->create(['name' => $name]);

        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
