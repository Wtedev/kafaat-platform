<?php

namespace Tests\Feature;

use App\Enums\PathStatus;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\StaffUi\StaffRegistrationAvailability;
use App\Enums\TrainingProgramKind;
use App\Models\LearningPath;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use App\Services\StaffUi\StaffProgramStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiProgramsListTest extends TestCase
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
        Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_programs_view_permission_opens_the_list_and_others_get_403(): void
    {
        $viewer = $this->staff(['programs.view']);
        $blocked = $this->staff();
        $this->makeProgram(['title' => 'برنامج ظاهر في القائمة']);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.index'))
            ->assertOk()
            ->assertSee('البرامج')
            ->assertSee('برنامج ظاهر في القائمة')
            ->assertDontSee('تطبيق');

        $this->actingAsOtpVerified($blocked)
            ->get(route('staff-ui.programs.index'))
            ->assertForbidden();
    }

    public function test_search_and_filters_work_alone_and_together_newest_first(): void
    {
        $viewer = $this->staff(['programs.view']);

        $older = $this->makeProgram([
            'title' => 'TITLE_OLDEST_COURSE',
            'program_kind' => TrainingProgramKind::Course,
            'status' => ProgramStatus::Published,
            'created_at' => now()->subDays(3),
        ]);
        $newer = $this->makeProgram([
            'title' => 'TITLE_MID_WORKSHOP_DRAFT',
            'program_kind' => TrainingProgramKind::Workshop,
            'status' => ProgramStatus::Draft,
            'published_at' => null,
            'created_at' => now()->subDay(),
        ]);
        $this->makeProgram([
            'title' => 'TITLE_NEWEST_FORUM',
            'program_kind' => TrainingProgramKind::Forum,
            'status' => ProgramStatus::Published,
            'created_at' => now(),
        ]);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'TITLE_NEWEST_FORUM',
                'TITLE_MID_WORKSHOP_DRAFT',
                'TITLE_OLDEST_COURSE',
            ]);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.index', ['q' => 'MID_WORKSHOP']))
            ->assertOk()
            ->assertSee('TITLE_MID_WORKSHOP_DRAFT')
            ->assertDontSee('TITLE_OLDEST_COURSE')
            ->assertDontSee('TITLE_NEWEST_FORUM');

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.index', ['kind' => TrainingProgramKind::Course->value]))
            ->assertOk()
            ->assertSee('TITLE_OLDEST_COURSE')
            ->assertDontSee('TITLE_MID_WORKSHOP_DRAFT');

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.index', ['status' => ProgramStatus::Draft->value]))
            ->assertOk()
            ->assertSee('TITLE_MID_WORKSHOP_DRAFT')
            ->assertDontSee('TITLE_OLDEST_COURSE');

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.index', [
                'q' => 'MID_WORKSHOP',
                'kind' => TrainingProgramKind::Workshop->value,
                'status' => ProgramStatus::Draft->value,
            ]))
            ->assertOk()
            ->assertSee('TITLE_MID_WORKSHOP_DRAFT')
            ->assertSee('مسح الفلاتر')
            ->assertDontSee('TITLE_NEWEST_FORUM');

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.index', ['q' => 'لا-يوجد']))
            ->assertOk()
            ->assertSee('لا توجد نتائج مطابقة للفلاتر.')
            ->assertSee('مسح الفلاتر');

        $this->assertNotNull($older->id);
        $this->assertNotNull($newer->id);
    }

    public function test_registration_availability_states_and_filters(): void
    {
        $viewer = $this->staff(['programs.view']);
        $status = app(StaffProgramStatus::class);

        $open = $this->makeProgram([
            'title' => 'REG_STATE_OPEN',
            'registration_start' => now()->subDay()->toDateString(),
            'registration_end' => now()->addMonth()->toDateString(),
            'capacity' => 10,
        ]);
        $notStarted = $this->makeProgram([
            'title' => 'REG_STATE_NOT_STARTED',
            'registration_start' => now()->addWeek()->toDateString(),
            'registration_end' => now()->addMonth()->toDateString(),
        ]);
        $closed = $this->makeProgram([
            'title' => 'REG_STATE_CLOSED',
            'registration_start' => now()->subMonth()->toDateString(),
            'registration_end' => now()->subDay()->toDateString(),
        ]);
        $full = $this->makeProgram([
            'title' => 'REG_STATE_FULL',
            'registration_start' => now()->subDay()->toDateString(),
            'registration_end' => now()->addMonth()->toDateString(),
            'capacity' => 1,
        ]);
        $this->registerApproved($full);

        $path = LearningPath::query()->create([
            'title' => 'PATH_TITLE_SKILLS',
            'slug' => 'path-skills-'.uniqid(),
            'status' => PathStatus::Published,
            'published_at' => now(),
        ]);
        $viaPath = $this->makeProgram([
            'title' => 'REG_STATE_PATH',
            'learning_path_id' => $path->id,
            'capacity' => null,
            'registration_start' => null,
            'registration_end' => null,
        ]);

        $this->assertSame(StaffRegistrationAvailability::Open, $status->registrationAvailability($open->fresh()));
        $this->assertSame(StaffRegistrationAvailability::NotStarted, $status->registrationAvailability($notStarted->fresh()));
        $this->assertSame(StaffRegistrationAvailability::Closed, $status->registrationAvailability($closed->fresh()));
        $fullLoaded = $full->fresh()->loadCount([
            'registrations as approved_registrations_count' => fn ($q) => $q->where('status', RegistrationStatus::Approved->value),
        ]);
        $this->assertSame(1, (int) $fullLoaded->approved_registrations_count);
        $this->assertSame(
            StaffRegistrationAvailability::Full,
            $status->registrationAvailability($fullLoaded),
        );
        $this->assertSame(StaffRegistrationAvailability::Path, $status->registrationAvailability($viaPath->fresh()));

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.index', ['registration' => 'open']))
            ->assertOk()
            ->assertSee('REG_STATE_OPEN')
            ->assertDontSee('REG_STATE_NOT_STARTED')
            ->assertDontSee('REG_STATE_CLOSED')
            ->assertDontSee('REG_STATE_FULL')
            ->assertDontSee('REG_STATE_PATH');

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.index', ['registration' => 'not_started']))
            ->assertOk()
            ->assertSee('REG_STATE_NOT_STARTED')
            ->assertDontSee('REG_STATE_OPEN');

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.index', ['registration' => 'closed']))
            ->assertOk()
            ->assertSee('REG_STATE_CLOSED')
            ->assertDontSee('REG_STATE_OPEN');

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.index', ['registration' => 'full']))
            ->assertOk()
            ->assertSee('REG_STATE_FULL')
            ->assertDontSee('REG_STATE_OPEN');

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.index', ['registration' => 'path']))
            ->assertOk()
            ->assertSee('REG_STATE_PATH')
            ->assertSee('PATH_TITLE_SKILLS')
            ->assertDontSee('REG_STATE_OPEN');
    }

    public function test_publication_labels_for_draft_scheduled_and_published(): void
    {
        $viewer = $this->staff(['programs.view']);
        $status = app(StaffProgramStatus::class);

        $draft = $this->makeProgram([
            'title' => 'مسودة ظاهرة',
            'status' => ProgramStatus::Draft,
            'published_at' => null,
        ]);
        $scheduled = $this->makeProgram([
            'title' => 'مجدول للنشر',
            'status' => ProgramStatus::Published,
            'published_at' => now()->addDays(5),
        ]);
        $published = $this->makeProgram([
            'title' => 'منشور منذ يومين',
            'status' => ProgramStatus::Published,
            'published_at' => now()->subDays(2),
        ]);

        $this->assertSame('مسودة', $status->publicationLabel($draft));
        $this->assertStringContainsString('مجدول للنشر في', $status->publicationLabel($scheduled));
        $this->assertStringStartsWith('نُشر', $status->publicationLabel($published));
        $this->assertStringContainsString('منذ', $status->publicationLabel($published));

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.index'))
            ->assertOk()
            ->assertSee('مسودة ظاهرة')
            ->assertSee('مسودة')
            ->assertSee('مجدول للنشر')
            ->assertSee('منشور منذ يومين');
    }

    public function test_empty_catalog_message_without_filters(): void
    {
        $viewer = $this->staff(['programs.view']);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.index'))
            ->assertOk()
            ->assertSee('لا توجد برامج بعد.');
    }

    public function test_placeholder_show_page_and_query_count_stays_flat(): void
    {
        $viewer = $this->staff(['programs.view']);
        $program = $this->makeProgram(['title' => 'برنامج للتفاصيل']);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.show', $program))
            ->assertOk()
            ->assertSee('صفحة البرنامج قيد البناء')
            ->assertSee('برنامج للتفاصيل');

        TrainingProgram::query()->delete();
        for ($i = 0; $i < 5; $i++) {
            $this->makeProgram(['title' => "برنامج استعلام {$i}", 'slug' => 'query-5-'.$i.'-'.uniqid()]);
        }
        $withFive = $this->countIndexQueries($viewer);

        TrainingProgram::query()->delete();
        for ($i = 0; $i < 50; $i++) {
            $this->makeProgram(['title' => "برنامج استعلام {$i}", 'slug' => 'query-50-'.$i.'-'.uniqid()]);
        }
        $withFifty = $this->countIndexQueries($viewer);

        $this->assertSame($withFive, $withFifty);
    }

    public function test_null_registration_dates_follow_public_open_rules(): void
    {
        $status = app(StaffProgramStatus::class);

        $openWithNulls = $this->makeProgram([
            'title' => 'بدون تواريخ تسجيل',
            'registration_start' => null,
            'registration_end' => null,
            'capacity' => null,
        ]);

        $this->assertTrue($openWithNulls->isRegistrationOpen());
        $this->assertSame(
            StaffRegistrationAvailability::Open,
            $status->registrationAvailability($openWithNulls),
        );
    }

    private function countIndexQueries(User $viewer): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.index'))
            ->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProgram(array $overrides = []): TrainingProgram
    {
        $createdAt = $overrides['created_at'] ?? now();
        unset($overrides['created_at']);

        $program = TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج اختبار',
            'slug' => 'staff-ui-prog-'.uniqid(),
            'program_kind' => TrainingProgramKind::Course,
            'status' => ProgramStatus::Published,
            'published_at' => now()->subDay(),
            'learning_path_id' => null,
            'registration_start' => now()->subDay()->toDateString(),
            'registration_end' => now()->addMonth()->toDateString(),
            'capacity' => 30,
        ], $overrides));

        if ($createdAt !== null) {
            TrainingProgram::query()->whereKey($program->id)->update([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            $program->refresh();
        }

        return $program;
    }

    private function registerApproved(TrainingProgram $program): void
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole(RbacCatalog::ROLE_BENEFICIARY);

        ProgramRegistration::withoutEvents(function () use ($program, $user): void {
            ProgramRegistration::query()->create([
                'training_program_id' => $program->id,
                'user_id' => $user->id,
                'status' => RegistrationStatus::Approved,
            ]);
        });
    }

    /**
     * @param  list<string>  $permissions
     * @param  array<string, mixed>  $overrides
     */
    private function staff(array $permissions = [], array $overrides = []): User
    {
        $staff = User::factory()->create(array_merge([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $overrides));
        $staff->assignRole(RbacCatalog::ROLE_STAFF);
        if ($permissions !== []) {
            $staff->givePermissionTo($permissions);
        }

        return $staff->fresh();
    }
}
