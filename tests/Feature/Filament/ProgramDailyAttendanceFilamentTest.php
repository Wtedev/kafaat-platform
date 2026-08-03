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
use App\Models\ProgramAttendance;
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

    public function test_daily_mode_marks_status_for_selected_prep_day(): void
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
            ->callTableAction('manualAttendance', $registration, [
                'status' => AttendanceStatus::Late->value,
                'notes' => 'وصلت متأخرة',
            ])
            ->assertNotified('تم تحديث الحضور');

        $this->assertDatabaseHas('program_attendance', [
            'program_registration_id' => $registration->id,
            'status' => AttendanceStatus::Late->value,
        ]);

        $this->assertSame(
            AttendanceStatus::Late,
            app(ProgramAttendanceService::class)->statusForDate($registration->fresh(['attendanceRecords']), '2026-08-03'),
        );
        $this->assertNull(
            app(ProgramAttendanceService::class)->statusForDate($registration->fresh(['attendanceRecords']), '2026-08-04'),
        );
    }

    public function test_bulk_present_and_matrix_mode_render(): void
    {
        $admin = $this->admin();
        $program = $this->program($admin);
        $this->addPrepDay($program, '2026-08-03');
        $a = $this->register($program, 'أ');
        $b = $this->register($program, 'ب');

        $this->withSession(['otp_verified' => true]);
        $this->actingAs($admin);

        Livewire::actingAs($admin)
            ->test(ProgramAttendanceRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->set('selectedPrepDate', '2026-08-03')
            ->callTableBulkAction('bulkPresent', [$a, $b])
            ->assertNotified();

        $this->assertSame(2, ProgramAttendance::query()->where('status', AttendanceStatus::Present->value)->count());

        Livewire::actingAs($admin)
            ->test(ProgramAttendanceRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->call('setAttendanceMode', 'matrix')
            ->assertSet('attendanceMode', 'matrix')
            ->assertSee('أغسطس')
            ->assertCanSeeTableRecords([$a, $b]);
    }

    public function test_prep_day_create_via_relation_manager(): void
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
            ->callAction(TestAction::make('create')->table(), [
                'prep_date' => '2026-08-05',
                'delivery_type' => ProgramPrepDayType::InPerson->value,
                'requires_attendance' => true,
            ]);

        $this->assertTrue(
            ProgramPrepDay::query()
                ->where('training_program_id', $program->id)
                ->whereDate('prep_date', '2026-08-05')
                ->where('requires_attendance', true)
                ->exists()
        );
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
            'delivery_mode' => ProgramDeliveryMode::InPerson,
            'venue' => 'القاعة',
            'status' => ProgramStatus::Published,
            'published_at' => now()->subDay(),
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'capacity' => 30,
            'auto_accept_registrations' => true,
        ]);
    }

    private function addPrepDay(TrainingProgram $program, string $date): void
    {
        ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => $date,
            'delivery_type' => ProgramPrepDayType::InPerson,
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
