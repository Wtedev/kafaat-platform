<?php

namespace Tests\Feature;

use App\Enums\AttendanceMarkSource;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Filament\Resources\TrainingProgramResource\Pages\ViewTrainingProgram;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramAttendanceLinksRelationManager;
use App\Livewire\Attendance\TrainerDesk;
use App\Models\ProgramAttendanceLink;
use App\Models\ProgramAttendanceMark;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Attendance\ProgramAttendanceLinkService;
use App\Services\Rbac\RbacCatalog;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class ProgramAttendanceLinksTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_an_approved_trainee_checks_in_once_per_link(): void
    {
        $program = $this->program();
        $user = $this->beneficiary('نورة', 'سعد');
        $registration = $this->register($program, $user, RegistrationStatus::Approved);
        $link = $this->openLink($program, 'اللقاء الأول');

        $this->actingAsOtpVerified($user)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('التحضير مفتوح')
            ->assertSee('اللقاء الأول')
            ->assertSee('سجّل حضوري');

        $this->actingAsOtpVerified($user)
            ->post(route('portal.attendance.check-in', $link->token))
            ->assertRedirect(route('portal.dashboard'));

        $this->assertDatabaseCount('program_attendance_marks', 1);
        $this->assertDatabaseHas('program_attendance_marks', [
            'program_attendance_link_id' => $link->id,
            'program_registration_id' => $registration->id,
            'source' => AttendanceMarkSource::Self->value,
        ]);

        $this->actingAsOtpVerified($user)
            ->from(route('portal.dashboard'))
            ->post(route('portal.attendance.check-in', $link->token))
            ->assertRedirect(route('portal.dashboard'))
            ->assertSessionHasErrors(['attendance' => ProgramAttendanceLinkService::ALREADY_MESSAGE]);

        $this->assertDatabaseCount('program_attendance_marks', 1);
        $this->actingAsOtpVerified($user)
            ->get(route('portal.dashboard'))
            ->assertDontSee('سجّل حضوري');
    }

    public function test_the_same_trainee_can_check_in_on_two_links(): void
    {
        $program = $this->program();
        $user = $this->beneficiary('نورة', 'سعد');
        $this->register($program, $user, RegistrationStatus::Approved);
        $first = $this->openLink($program, 'اللقاء الأول');
        $second = $this->openLink($program, 'اللقاء الثاني');

        $this->actingAsOtpVerified($user)->post(route('portal.attendance.check-in', $first->token))->assertRedirect();
        $this->actingAsOtpVerified($user)->post(route('portal.attendance.check-in', $second->token))->assertRedirect();

        $this->assertDatabaseCount('program_attendance_marks', 2);
        $this->assertSame(2, ProgramAttendanceMark::query()->whereIn('program_attendance_link_id', [$first->id, $second->id])->count());
    }

    public function test_a_non_approved_registration_cannot_check_in(): void
    {
        $program = $this->program();
        $user = $this->beneficiary('نورة', 'سعد');
        $this->register($program, $user, RegistrationStatus::Pending);
        $link = $this->openLink($program, 'اللقاء الأول');

        $this->actingAsOtpVerified($user)
            ->from(route('portal.dashboard'))
            ->post(route('portal.attendance.check-in', $link->token))
            ->assertSessionHasErrors(['attendance' => ProgramAttendanceLinkService::NOT_APPROVED_MESSAGE]);

        $this->assertDatabaseCount('program_attendance_marks', 0);
        $this->actingAsOtpVerified($user)
            ->get(route('portal.dashboard'))
            ->assertDontSee('التحضير مفتوح');
    }

    public function test_check_in_is_rejected_after_the_window_closes(): void
    {
        $program = $this->program();
        $user = $this->beneficiary('نورة', 'سعد');
        $this->register($program, $user, RegistrationStatus::Approved);
        $service = app(ProgramAttendanceLinkService::class);
        $link = $service->create($program, 'اللقاء الأول');

        $this->actingAsOtpVerified($user)
            ->from(route('portal.dashboard'))
            ->post(route('portal.attendance.check-in', $link->token))
            ->assertSessionHasErrors(['attendance' => ProgramAttendanceLinkService::CLOSED_MESSAGE]);

        $service->open($link);
        $service->close($link->fresh());

        $this->actingAsOtpVerified($user)
            ->from(route('portal.dashboard'))
            ->post(route('portal.attendance.check-in', $link->token))
            ->assertSessionHasErrors(['attendance' => ProgramAttendanceLinkService::CLOSED_MESSAGE]);

        $reopened = $service->open($link->fresh());
        Carbon::setTestNow($reopened->closes_at);
        $this->actingAsOtpVerified($user)
            ->from(route('portal.dashboard'))
            ->post(route('portal.attendance.check-in', $link->token))
            ->assertSessionHasErrors(['attendance' => ProgramAttendanceLinkService::CLOSED_MESSAGE]);
        $this->assertSame(ProgramAttendanceLinkService::OPEN_MINUTES, (int) $reopened->opens_at->diffInMinutes($reopened->closes_at));
        $this->assertDatabaseCount('program_attendance_marks', 0);
    }

    public function test_a_cancelled_link_rejects_check_in(): void
    {
        $program = $this->program();
        $user = $this->beneficiary('نورة', 'سعد');
        $this->register($program, $user, RegistrationStatus::Approved);
        $service = app(ProgramAttendanceLinkService::class);
        $link = $this->openLink($program, 'اللقاء الأول');
        $service->cancel($link->fresh());

        $this->actingAsOtpVerified($user)
            ->from(route('portal.dashboard'))
            ->post(route('portal.attendance.check-in', $link->token))
            ->assertSessionHasErrors(['attendance' => ProgramAttendanceLinkService::CANCELLED_MESSAGE]);

        $this->get(route('public.attendance.desk', $link->token))
            ->assertOk()
            ->assertSee('رابط التحضير ملغى')
            ->assertDontSee('فتح التحضير');
        $this->assertDatabaseCount('program_attendance_marks', 0);
    }

    public function test_the_trainer_can_mark_attendance_manually_while_closed(): void
    {
        $moment = Carbon::parse('2026-10-10 16:11:03');
        Carbon::setTestNow($moment);
        $program = $this->program();
        $user = $this->beneficiary('نورة', 'سعد');
        $registration = $this->register($program, $user, RegistrationStatus::Approved);
        $link = app(ProgramAttendanceLinkService::class)->create($program, 'اللقاء الأول');

        $this->actingAsOtpVerified($user)
            ->from(route('portal.dashboard'))
            ->post(route('portal.attendance.check-in', $link->token))
            ->assertSessionHasErrors(['attendance' => ProgramAttendanceLinkService::CLOSED_MESSAGE]);

        Livewire::test(TrainerDesk::class, ['token' => $link->token])
            ->assertSee('wire:poll.3s', false)
            ->assertDontSee($user->email)
            ->set('tab', 'manual')
            ->assertSee('نورة سعد')
            ->call('mark', $registration->id)
            ->assertSee('حاضر');

        $mark = ProgramAttendanceMark::query()->firstOrFail();
        $this->assertSame(AttendanceMarkSource::Manual, $mark->source);
        $this->assertTrue($mark->attended_at->equalTo($moment));
    }

    public function test_the_check_in_time_is_stored_exactly_and_the_name_fades_after_four_seconds(): void
    {
        $moment = Carbon::parse('2026-10-10 16:05:07');
        Carbon::setTestNow($moment);
        $program = $this->program();
        $user = $this->beneficiary('نورة', 'سعد');
        $this->register($program, $user, RegistrationStatus::Approved);
        $link = $this->openLink($program, 'اللقاء الأول');

        $this->actingAsOtpVerified($user)
            ->post(route('portal.attendance.check-in', $link->token))
            ->assertRedirect();

        $mark = ProgramAttendanceMark::query()->firstOrFail();
        $this->assertTrue($mark->attended_at->equalTo($moment));
        $this->assertSame(AttendanceMarkSource::Self, $mark->source);

        auth()->logout();

        $this->get(route('public.attendance.desk', $link->token))
            ->assertOk()
            ->assertSee('نورة سعد')
            ->assertSee('1 من 1')
            ->assertDontSee($user->email);

        Carbon::setTestNow($moment->copy()->addSeconds(5));
        $this->get(route('public.attendance.desk', $link->token))
            ->assertOk()
            ->assertDontSee('نورة سعد')
            ->assertSee('1 من 1');
    }

    public function test_attendance_time_is_shown_in_riyadh_on_the_trainer_page_and_in_filament(): void
    {
        $previousConfig = config('app.timezone');
        $previousPhp = date_default_timezone_get();
        config(['app.timezone' => 'UTC']);
        date_default_timezone_set('UTC');
        Carbon::setTestNow(Carbon::parse('2026-10-10 13:05:07', 'UTC'));

        try {
            $program = $this->program();
            $user = $this->beneficiary('نورة', 'سعد');
            $this->register($program, $user, RegistrationStatus::Approved);
            $link = $this->openLink($program, 'اللقاء الأول');

            $this->actingAsOtpVerified($user)
                ->post(route('portal.attendance.check-in', $link->token))
                ->assertRedirect();

            auth()->logout();

            $this->get(route('public.attendance.desk', $link->token))
                ->assertOk()
                ->assertSee('2026-10-10 16:05:07')
                ->assertDontSee('2026-10-10 13:05:07');

            Livewire::test(TrainerDesk::class, ['token' => $link->token])
                ->set('tab', 'manual')
                ->assertSee('2026-10-10 16:05:07')
                ->assertDontSee('2026-10-10 13:05:07');

            Filament::setCurrentPanel(Filament::getPanel('admin'));
            $admin = User::factory()->create([
                'role_type' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $admin->assignRole(RbacCatalog::ROLE_ADMIN);
            $this->withSession(['otp_verified' => true]);
            $this->actingAs($admin);

            $component = Livewire::actingAs($admin)
                ->test(ProgramAttendanceLinksRelationManager::class, [
                    'ownerRecord' => $program,
                    'pageClass' => ViewTrainingProgram::class,
                ])
                ->mountAction(TestAction::make('attendees')->table($link));

            $html = (string) $component->instance()->getMountedAction()->getModalContent();
            $this->assertStringContainsString('2026-10-10 16:05:07', $html);
            $this->assertStringNotContainsString('2026-10-10 13:05:07', $html);
        } finally {
            Carbon::setTestNow();
            config(['app.timezone' => $previousConfig]);
            date_default_timezone_set($previousPhp);
        }
    }

    public function test_staff_can_create_and_cancel_an_attendance_link(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(RbacCatalog::ROLE_ADMIN);
        $program = $this->program();

        $this->withSession(['otp_verified' => true]);
        $this->actingAs($admin);

        Livewire::actingAs($admin)
            ->test(ProgramAttendanceLinksRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->callAction(TestAction::make('create')->table(), [
                'name' => 'اللقاء الأول – د. عبدالله العمير',
            ])
            ->assertSee('اللقاء الأول – د. عبدالله العمير')
            ->assertSee('0');

        $link = ProgramAttendanceLink::query()->firstOrFail();
        $this->assertSame(40, strlen($link->token));

        Livewire::actingAs($admin)
            ->test(ProgramAttendanceLinksRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->callAction(TestAction::make('cancel')->table($link));

        $this->assertNotNull($link->fresh()->cancelled_at);
    }

    private function program(): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => 'ملتقى تحليل البيانات',
            'slug' => 'attendance-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'registration_start' => now()->subDay()->toDateString(),
            'registration_end' => now()->addMonth()->toDateString(),
        ]);
    }

    private function beneficiary(string $first, string $family): User
    {
        $user = User::factory()->create([
            'name' => $first.' '.$family,
            'first_name' => $first,
            'father_name' => null,
            'grandfather_name' => null,
            'family_name' => $family,
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('beneficiary');

        return $user->fresh();
    }

    private function register(TrainingProgram $program, User $user, RegistrationStatus $status): ProgramRegistration
    {
        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => $status,
        ]);
    }

    private function openLink(TrainingProgram $program, string $name): ProgramAttendanceLink
    {
        $link = app(ProgramAttendanceLinkService::class)->create($program, $name);
        app(ProgramAttendanceLinkService::class)->open($link);

        return $link->fresh();
    }
}
