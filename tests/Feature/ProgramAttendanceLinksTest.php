<?php

namespace Tests\Feature;

use App\Enums\AttendanceMarkSource;
use App\Enums\IdentityType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Exports\ProgramAttendanceMarkExport;
use App\Filament\Resources\TrainingProgramResource\Pages\ViewTrainingProgram;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramAttendanceLinksRelationManager;
use App\Livewire\Attendance\TrainerDesk;
use App\Models\ProgramAttendanceLink;
use App\Models\ProgramAttendanceMark;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Attendance\ProgramAttendanceLinkService;
use App\Services\Identity\IdentityNumberService;
use App\Services\Rbac\RbacCatalog;
use App\Services\Surveys\ProgramSurveyService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
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

    public function test_an_approved_trainee_checks_in_once_per_link_without_logging_in(): void
    {
        $program = $this->program();
        $identity = '1000000001';
        $user = $this->beneficiary('نورة', 'سعد', $identity);
        $registration = $this->register($program, $user, RegistrationStatus::Approved);
        $link = $this->openLink($program, 'اليوم الأول');

        $this->assertGuest();
        $this->get(route('public.attendance.show', $link->token))
            ->assertOk()
            ->assertSee('اليوم الأول')
            ->assertSee($program->title)
            ->assertSee('رقم الهوية');

        $this->post(route('public.attendance.identify', $link->token), [
            'national_id' => $identity,
            'turnstile_token' => 'test-turnstile',
        ])->assertRedirect(route('public.attendance.show', $link->token));

        $this->get(route('public.attendance.show', $link->token))
            ->assertOk()
            ->assertSee('نو')
            ->assertSee('تأكيد الحضور');

        $this->post(route('public.attendance.confirm', $link->token))
            ->assertOk()
            ->assertSee(ProgramAttendanceLinkService::RECORDED_MESSAGE);

        $this->assertGuest();
        $this->assertDatabaseCount('program_attendance_marks', 1);
        $this->assertDatabaseHas('program_attendance_marks', [
            'program_attendance_link_id' => $link->id,
            'program_registration_id' => $registration->id,
            'source' => AttendanceMarkSource::PublicLink->value,
        ]);
        $this->assertNotNull(ProgramAttendanceMark::query()->firstOrFail()->ip_address);
        $export = new ProgramAttendanceMarkExport($link->fresh());
        $this->assertSame(['الاسم', 'الوقت', 'المصدر'], $export->headings());
        $this->assertSame('رابط عام', $export->collection()->first()[2]);
        $this->assertSame('نورة سعد', $export->collection()->first()[0]);

        $this->post(route('public.attendance.identify', $link->token), [
            'national_id' => $identity,
            'turnstile_token' => 'test-turnstile',
        ])->assertOk()->assertSee(ProgramAttendanceLinkService::ALREADY_PUBLIC_MESSAGE);

        $this->assertDatabaseCount('program_attendance_marks', 1);
        $this->actingAsOtpVerified($user)
            ->get(route('portal.dashboard'))
            ->assertDontSee('سجّل حضوري');
    }

    public function test_the_same_trainee_can_check_in_on_two_links(): void
    {
        $program = $this->program();
        $identity = '1000000002';
        $user = $this->beneficiary('نورة', 'سعد', $identity);
        $this->register($program, $user, RegistrationStatus::Approved);
        $first = $this->openLink($program, 'اللقاء الأول');
        $second = $this->openLink($program, 'اللقاء الثاني');

        $this->attend($first, $identity)->assertOk();
        $this->attend($second, $identity)->assertOk();

        $this->assertDatabaseCount('program_attendance_marks', 2);
    }

    public function test_a_non_approved_or_unknown_identity_is_rejected_the_same_way(): void
    {
        $program = $this->program();
        $identity = '1000000003';
        $user = $this->beneficiary('نورة', 'سعد', $identity);
        $this->register($program, $user, RegistrationStatus::Pending);
        $link = $this->openLink($program, 'اليوم الأول');

        $this->from(route('public.attendance.show', $link->token))
            ->post(route('public.attendance.identify', $link->token), [
                'national_id' => $identity,
                'turnstile_token' => 'test-turnstile',
            ])
            ->assertSessionHasErrors(['national_id' => ProgramAttendanceLinkService::NOT_FOUND_MESSAGE]);

        $this->from(route('public.attendance.show', $link->token))
            ->post(route('public.attendance.identify', $link->token), [
                'national_id' => '1000000099',
                'turnstile_token' => 'test-turnstile',
            ])
            ->assertSessionHasErrors(['national_id' => ProgramAttendanceLinkService::NOT_FOUND_MESSAGE]);

        $this->assertDatabaseCount('program_attendance_marks', 0);
    }

    public function test_turnstile_is_required_and_the_identity_and_ip_limits_apply(): void
    {
        $program = $this->program();
        $link = $this->openLink($program, 'اليوم الأول');

        $this->from(route('public.attendance.show', $link->token))
            ->post(route('public.attendance.identify', $link->token), [
                'national_id' => '1000000004',
            ])
            ->assertSessionHasErrors(['turnstile_token' => ProgramSurveyService::TURNSTILE_MESSAGE]);

        $identity = '1000000005';
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->from(route('public.attendance.show', $link->token))
                ->post(route('public.attendance.identify', $link->token), [
                    'national_id' => $identity,
                    'turnstile_token' => 'test-turnstile',
                ])
                ->assertSessionHasErrors(['national_id' => ProgramAttendanceLinkService::NOT_FOUND_MESSAGE]);
        }

        $this->from(route('public.attendance.show', $link->token))
            ->post(route('public.attendance.identify', $link->token), [
                'national_id' => $identity,
                'turnstile_token' => 'test-turnstile',
            ])
            ->assertSessionHasErrors(['national_id' => 'تجاوزت عدد المحاولات. حاول بعد دقيقة.']);
    }

    public function test_requests_from_one_ip_are_limited_at_thirty_per_minute(): void
    {
        $program = $this->program();
        $link = $this->openLink($program, 'اليوم الأول');

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->from(route('public.attendance.show', $link->token))
                ->post(route('public.attendance.identify', $link->token), [
                    'national_id' => sprintf('1%09d', 300000000 + $attempt),
                    'turnstile_token' => 'test-turnstile',
                ])
                ->assertSessionHasErrors(['national_id' => ProgramAttendanceLinkService::NOT_FOUND_MESSAGE]);
        }

        $this->from(route('public.attendance.show', $link->token))
            ->post(route('public.attendance.identify', $link->token), [
                'national_id' => '1000000777',
                'turnstile_token' => 'test-turnstile',
            ])
            ->assertSessionHasErrors(['national_id' => 'تجاوزت عدد المحاولات. حاول بعد دقيقة.']);
    }

    public function test_check_in_is_rejected_after_the_window_closes(): void
    {
        $program = $this->program();
        $user = $this->beneficiary('نورة', 'سعد');
        $this->register($program, $user, RegistrationStatus::Approved);
        $service = app(ProgramAttendanceLinkService::class);
        $link = $service->create($program, 'اللقاء الأول');

        $this->get(route('public.attendance.show', $link->token))
            ->assertOk()
            ->assertSee(ProgramAttendanceLinkService::UNAVAILABLE_MESSAGE)
            ->assertDontSee('name="national_id"', false);

        $service->open($link);
        $service->close($link->fresh());

        $this->get(route('public.attendance.show', $link->fresh()->token))
            ->assertOk()
            ->assertSee(ProgramAttendanceLinkService::UNAVAILABLE_MESSAGE);

        $reopened = $service->open($link->fresh());
        $this->assertSame(15, (int) $reopened->opens_at->diffInMinutes($reopened->closes_at));
        Carbon::setTestNow($reopened->closes_at);
        $this->get(route('public.attendance.show', $link->token))
            ->assertOk()
            ->assertSee(ProgramAttendanceLinkService::UNAVAILABLE_MESSAGE);

        $custom = $service->create($program, 'اليوم الأول', 20);
        $customOpened = $service->open($custom);
        $this->assertSame(20, (int) $customOpened->opens_at->diffInMinutes($customOpened->closes_at));
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

        $this->get(route('public.attendance.show', $link->token))
            ->assertOk()
            ->assertSee(ProgramAttendanceLinkService::UNAVAILABLE_MESSAGE);

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

        $this->get(route('public.attendance.show', $link->token))
            ->assertOk()
            ->assertSee(ProgramAttendanceLinkService::UNAVAILABLE_MESSAGE);

        Livewire::test(TrainerDesk::class, ['token' => $link->token])
            ->assertSee('wire:poll.3s', false)
            ->assertDontSee($user->email)
            ->set('tab', 'manual')
            ->assertSee('نورة سعد')
            ->call('mark', $registration->id)
            ->assertSee('تحضير');

        $mark = ProgramAttendanceMark::query()->firstOrFail();
        $this->assertSame(AttendanceMarkSource::Manual, $mark->source);
        $this->assertTrue($mark->attended_at->equalTo($moment));
    }

    public function test_the_check_in_time_is_stored_exactly_and_the_name_fades_after_four_seconds(): void
    {
        $moment = Carbon::parse('2026-10-10 16:05:07');
        Carbon::setTestNow($moment);
        $program = $this->program();
        $identity = '1000000011';
        $user = $this->beneficiary('نورة', 'سعد', $identity);
        $this->register($program, $user, RegistrationStatus::Approved);
        $link = $this->openLink($program, 'اللقاء الأول');

        $this->attend($link, $identity)->assertOk();

        $mark = ProgramAttendanceMark::query()->firstOrFail();
        $this->assertTrue($mark->attended_at->equalTo($moment));
        $this->assertSame(AttendanceMarkSource::PublicLink, $mark->source);

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
            $identity = '1000000012';
            $user = $this->beneficiary('نورة', 'سعد', $identity);
            $this->register($program, $user, RegistrationStatus::Approved);
            $link = $this->openLink($program, 'اللقاء الأول');

            $this->attend($link, $identity)->assertOk()->assertSee('2026-10-10 16:05:07');

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

    public function test_staff_can_mark_a_registrant_manually_from_the_link_and_sees_the_name(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(RbacCatalog::ROLE_ADMIN);
        $program = $this->program();
        $identity = '1123456789';
        $user = $this->beneficiary('نورة', 'سعد', $identity);
        $registration = $this->register($program, $user, RegistrationStatus::Approved);
        $link = app(ProgramAttendanceLinkService::class)->create($program, 'السبت 10 اكتوبر');

        $this->withSession(['otp_verified' => true]);
        $this->actingAs($admin);

        $component = Livewire::actingAs($admin)
            ->test(ProgramAttendanceLinksRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->mountAction(TestAction::make('manual')->table($link))
            ->fillForm(['national_id' => $identity]);
        $this->assertSame('نورة سعد', $this->manualAttendanceName($component));
        $component->callMountedAction()->assertHasNoFormErrors();

        $mark = ProgramAttendanceMark::query()->where('program_registration_id', $registration->id)->first();
        $this->assertNotNull($mark);
        $this->assertSame(AttendanceMarkSource::Manual, $mark->source);
        $this->assertSame(1, ProgramAttendanceMark::query()->count());

        $again = Livewire::actingAs($admin)
            ->test(ProgramAttendanceLinksRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->mountAction(TestAction::make('manual')->table($link))
            ->fillForm(['national_id' => $identity]);
        $this->assertSame(
            'نورة سعد — '.ProgramAttendanceLinkService::ALREADY_MESSAGE,
            $this->manualAttendanceName($again),
        );
        $again->callMountedAction()->assertHasErrors(['national_id']);

        $this->assertSame(1, ProgramAttendanceMark::query()->count());

        $missing = Livewire::actingAs($admin)
            ->test(ProgramAttendanceLinksRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->mountAction(TestAction::make('manual')->table($link->fresh()))
            ->fillForm(['national_id' => '1099887766']);
        $this->assertSame(ProgramAttendanceLinkService::NOT_FOUND_MESSAGE, $this->manualAttendanceName($missing));
        $missing->callMountedAction()->assertHasErrors(['national_id']);
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

    private function manualAttendanceName(mixed $component): mixed
    {
        $action = $component->instance()->getMountedAction();
        $schema = $action->getSchema(Schema::make($component->instance()));

        foreach ($schema->getComponents(withHidden: true) as $field) {
            if ($field->getName() === 'matched_name') {
                return $field->getState();
            }
        }

        return null;
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

    private function beneficiary(string $first, string $family, ?string $identity = null): User
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
        if ($identity !== null) {
            $user->forceFill(IdentityNumberService::prepareStoragePayload($identity, IdentityType::NationalId))->save();
        }
        $user->assignRole('beneficiary');

        return $user->fresh();
    }

    private function attend(ProgramAttendanceLink $link, string $identity): TestResponse
    {
        $this->post(route('public.attendance.identify', $link->token), [
            'national_id' => $identity,
            'turnstile_token' => 'test-turnstile',
        ])->assertRedirect(route('public.attendance.show', $link->token));

        return $this->post(route('public.attendance.confirm', $link->token));
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
