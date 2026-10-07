<?php

namespace Tests\Feature;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Models\LearningPath;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiProgramsIndexTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00', config('app.timezone')));
        config([
            'staff_ui.maintenance' => false,
            'staff_ui.ready_modules' => ['users', 'training'],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_programs_link_requires_programs_view_and_lists_every_program(): void
    {
        $viewer = $this->staff(['programs.view']);
        $outsider = $this->staff(['users.view']);
        $owner = $this->staff(['programs.view'], ['email' => 'owner.programs@example.com']);
        $mine = $this->program(['title' => 'برنامج المالك', 'owner_id' => $owner->id, 'assigned_to' => $owner->id]);
        $theirs = $this->program(['title' => 'برنامج زميل', 'owner_id' => $owner->id, 'assigned_to' => $owner->id]);

        $this->get(route('staff-ui.programs.index'))->assertRedirect(route('login'));

        $this->actingAsOtpVerified($this->beneficiary())
            ->get(route('staff-ui.programs.index'))
            ->assertForbidden();

        $this->actingAsOtpVerified($outsider)
            ->get(route('staff-ui.programs.index'))
            ->assertForbidden();

        $this->actingAsOtpVerified($outsider)
            ->get(route('staff-ui.users.index'))
            ->assertOk()
            ->assertDontSee(route('staff-ui.programs.index'), false);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.index'))
            ->assertOk()
            ->assertSee(route('staff-ui.programs.index'), false)
            ->assertSee('برنامج المالك')
            ->assertSee('برنامج زميل')
            ->assertSee('بطاقات')
            ->assertSee('جدول');

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.show', $theirs))
            ->assertOk()
            ->assertSee('صفحة البرنامج قيد البناء')
            ->assertSee('برنامج زميل');

        $this->actingAsOtpVerified($outsider)
            ->get(route('staff-ui.programs.show', $mine))
            ->assertForbidden();
    }

    public function test_search_and_filters_work_alone_and_together(): void
    {
        $staff = $this->staff(['programs.view']);
        $this->program([
            'title' => 'دورة القياس',
            'description' => 'وصف لا يُبحث',
            'program_kind' => TrainingProgramKind::Course,
            'status' => ProgramStatus::Published,
            'published_at' => now()->subDay(),
        ]);
        $this->program([
            'title' => 'ورشة القياس',
            'description' => 'كلمة سرية في الوصف',
            'program_kind' => TrainingProgramKind::Workshop,
            'status' => ProgramStatus::Draft,
        ]);
        $this->program([
            'title' => 'ملتقى آخر',
            'program_kind' => TrainingProgramKind::Forum,
            'status' => ProgramStatus::Archived,
        ]);

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.programs.index', ['q' => 'القياس']))
            ->assertOk()
            ->assertSee('دورة القياس')
            ->assertSee('ورشة القياس')
            ->assertDontSee('ملتقى آخر');

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.programs.index', ['q' => 'سرية']))
            ->assertOk()
            ->assertSee('لا توجد برامج مطابقة للبحث أو الفلاتر.')
            ->assertSee('مسح الفلاتر')
            ->assertDontSee('ورشة القياس');

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.programs.index', ['kind' => TrainingProgramKind::Workshop->value]))
            ->assertOk()
            ->assertSee('ورشة القياس')
            ->assertDontSee('دورة القياس')
            ->assertDontSee('ملتقى آخر');

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.programs.index', ['status' => ProgramStatus::Published->value]))
            ->assertOk()
            ->assertSee('دورة القياس')
            ->assertDontSee('ورشة القياس')
            ->assertDontSee('ملتقى آخر');

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.programs.index', [
                'q' => 'القياس',
                'kind' => TrainingProgramKind::Course->value,
                'status' => ProgramStatus::Published->value,
            ]))
            ->assertOk()
            ->assertSee('دورة القياس')
            ->assertDontSee('ورشة القياس')
            ->assertDontSee('ملتقى آخر')
            ->assertSee('name="q"', false)
            ->assertSee('القياس');
    }

    public function test_empty_catalog_is_distinct_from_an_empty_filter(): void
    {
        $staff = $this->staff(['programs.view']);

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.programs.index'))
            ->assertOk()
            ->assertSee('لا توجد برامج بعد.')
            ->assertDontSee('مسح الفلاتر');
    }

    public function test_programs_are_newest_first_and_paginated(): void
    {
        $staff = $this->staff(['programs.view']);
        $oldest = $this->program(['title' => 'البرنامج الأقدم']);
        $middle = $this->program(['title' => 'البرنامج الأوسط']);
        $newest = $this->program(['title' => 'البرنامج الأحدث']);
        $oldest->forceFill(['created_at' => now()->subDays(3)])->save();
        $middle->forceFill(['created_at' => now()->subDays(2)])->save();
        $newest->forceFill(['created_at' => now()->subDay()])->save();

        foreach (range(1, 13) as $index) {
            $extra = $this->program(['title' => 'برنامج إضافي '.$index]);
            $extra->forceFill(['created_at' => now()->subMinutes($index)])->save();
        }

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.programs.index'))
            ->assertOk()
            ->assertSeeInOrder(['البرنامج الأحدث', 'البرنامج الأوسط'])
            ->assertDontSee('البرنامج الأقدم')
            ->assertSee('التالي');

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.programs.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('البرنامج الأقدم')
            ->assertDontSee('البرنامج الأحدث');
    }

    public function test_registration_states_and_counts_follow_the_window_and_approved_capacity(): void
    {
        $staff = $this->staff(['programs.view']);

        $open = $this->program([
            'title' => 'تسجيل مفتوح',
            'capacity' => 30,
            'registration_start' => '2026-10-01',
            'registration_end' => '2026-10-20',
            'start_date' => '2026-11-01',
        ]);
        $this->registrations($open, [RegistrationStatus::Pending, RegistrationStatus::Pending, RegistrationStatus::Approved, RegistrationStatus::Completed, RegistrationStatus::Rejected]);

        $upcoming = $this->program([
            'title' => 'تسجيل لم يبدأ',
            'capacity' => 1,
            'registration_start' => '2026-10-08',
            'registration_end' => '2026-10-20',
        ]);
        $this->registrations($upcoming, [RegistrationStatus::Approved]);

        $closed = $this->program([
            'title' => 'تسجيل مغلق',
            'capacity' => 1,
            'registration_start' => '2026-09-01',
            'registration_end' => '2026-10-06',
        ]);
        $this->registrations($closed, [RegistrationStatus::Approved]);

        $ended = $this->program([
            'title' => 'برنامج منتهٍ',
            'capacity' => 10,
            'registration_start' => '2026-09-01',
            'registration_end' => '2026-10-20',
            'end_date' => '2026-10-06',
        ]);

        $full = $this->program([
            'title' => 'تسجيل مكتمل',
            'capacity' => 2,
            'registration_start' => null,
            'registration_end' => null,
        ]);
        $this->registrations($full, [RegistrationStatus::Approved, RegistrationStatus::Approved, RegistrationStatus::Pending, RegistrationStatus::Completed]);

        $unlimited = $this->program([
            'title' => 'سعة مفتوحة',
            'capacity' => null,
        ]);
        $this->registrations($unlimited, [RegistrationStatus::Approved, RegistrationStatus::Pending]);

        $path = LearningPath::query()->create([
            'title' => 'مسار البرامج',
            'slug' => 'staff-ui-programs-path',
            'competency_track' => CompetencyTrack::Community,
            'status' => 'draft',
        ]);
        $linked = $this->program([
            'title' => 'برنامج داخل مسار',
            'learning_path_id' => $path->id,
        ]);

        $response = $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.programs.index'))
            ->assertOk()
            ->assertSee('1 / 30')
            ->assertSee('2 / 2')
            ->assertSee('1 / غير محدود')
            ->assertSee('مكتمل العدد')
            ->assertSee('عبر المسار');

        $this->assertProgramState($response, 'تسجيل مفتوح', 'open');
        $this->assertMatchesRegularExpression('/data-pending="2"[\s\S]{0,2000}تسجيل مفتوح/u', $response->getContent());
        $this->assertMatchesRegularExpression('/data-accepted="1 \/ 30"[\s\S]{0,2000}تسجيل مفتوح/u', $response->getContent());
        $this->assertMatchesRegularExpression('/data-pending="1"[\s\S]{0,2000}تسجيل مكتمل/u', $response->getContent());
        $this->assertProgramState($response, 'تسجيل لم يبدأ', 'upcoming');
        $this->assertProgramState($response, 'تسجيل مغلق', 'closed');
        $this->assertProgramState($response, 'برنامج منتهٍ', 'closed');
        $this->assertProgramState($response, 'تسجيل مكتمل', 'full');
        $this->assertProgramState($response, 'سعة مفتوحة', 'open');
        $this->assertProgramState($response, 'برنامج داخل مسار', 'path');
        $this->assertNull($linked->fresh()->capacity);
    }

    public function test_publication_line_covers_relative_draft_and_scheduled(): void
    {
        $staff = $this->staff(['programs.view']);
        $this->program([
            'title' => 'نُشر قبل يومين',
            'status' => ProgramStatus::Published,
            'published_at' => now()->subDays(2),
        ]);
        $this->program([
            'title' => 'نُشر قبل ثلاثة أسابيع',
            'status' => ProgramStatus::Published,
            'published_at' => now()->subDays(21),
        ]);
        $this->program([
            'title' => 'مسودة عادية',
            'status' => ProgramStatus::Draft,
            'published_at' => now()->subDay(),
        ]);
        $this->program([
            'title' => 'نشر مجدول',
            'status' => ProgramStatus::Draft,
            'published_at' => Carbon::parse('2026-10-20 00:00:00', config('app.timezone')),
        ]);

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.programs.index'))
            ->assertOk()
            ->assertSee('نُشر منذ يومين')
            ->assertSee('نُشر منذ 3 أسابيع')
            ->assertSee('مسودة')
            ->assertSee('مجدول للنشر في 2026/10/20');

        $html = $this->actingAsOtpVerified($staff)->get(route('staff-ui.programs.index'))->getContent();
        $this->assertMatchesRegularExpression('/data-publication="نُشر منذ يومين"[\s\S]{0,2000}نُشر قبل يومين/u', $html);
        $this->assertMatchesRegularExpression('/data-publication="نُشر منذ 3 أسابيع"[\s\S]{0,2000}نُشر قبل ثلاثة أسابيع/u', $html);
        $this->assertMatchesRegularExpression('/data-publication=""[\s\S]{0,2000}مسودة عادية/u', $html);
        $this->assertMatchesRegularExpression('/data-publication="مجدول للنشر في 2026\/10\/20"[\s\S]{0,2000}نشر مجدول/u', $html);
    }

    public function test_listing_query_count_does_not_grow_with_program_count(): void
    {
        $staff = $this->staff(['programs.view']);
        $this->programs(5);
        $this->actingAsOtpVerified($staff)->get(route('staff-ui.programs.index'))->assertOk();

        $five = $this->queryCount($staff);
        $this->programs(45);
        $fifty = $this->queryCount($staff);

        $this->assertSame($five, $fifty);
        $this->assertGreaterThan(0, $five);
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

    private function beneficiary(): User
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole(RbacCatalog::ROLE_BENEFICIARY);

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function program(array $overrides = []): TrainingProgram
    {
        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج '.str()->random(8),
            'description' => 'وصف',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Self,
            'delivery_mode' => ProgramDeliveryMode::Remote,
            'status' => ProgramStatus::Draft,
            'notify_on_publish' => false,
            'capacity' => 20,
            'auto_accept_registrations' => false,
        ], $overrides));
    }

    private function programs(int $count): void
    {
        foreach (range(1, $count) as $index) {
            $this->program(['title' => 'برنامج العدد '.$index.' '.str()->random(4)]);
        }
    }

    /**
     * @param  list<RegistrationStatus>  $statuses
     */
    private function registrations(TrainingProgram $program, array $statuses): void
    {
        foreach ($statuses as $status) {
            $user = User::factory()->create([
                'role_type' => 'beneficiary',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            ProgramRegistration::query()->create([
                'training_program_id' => $program->id,
                'user_id' => $user->id,
                'status' => $status,
            ]);
        }
    }

    private function assertProgramState(TestResponse $response, string $title, string $key): void
    {
        $this->assertMatchesRegularExpression(
            '/data-registration="'.preg_quote($key, '/').'"[\s\S]{0,2000}'.preg_quote($title, '/').'/u',
            $response->getContent()
        );
    }

    private function queryCount(User $staff): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $this->actingAsOtpVerified($staff)
                ->get(route('staff-ui.programs.index'))
                ->assertOk();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
}
