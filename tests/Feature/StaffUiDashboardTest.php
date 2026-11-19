<?php

namespace Tests\Feature;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Attendance\ProgramAttendanceLinkService;
use App\Services\Rbac\RbacCatalog;
use App\Services\StaffAlerts\StaffAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiDashboardTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        config(['staff_ui.maintenance' => false]);
    }

    public function test_dashboard_shows_what_needs_doing_and_todays_programs(): void
    {
        $admin = User::factory()->create(['name' => 'لمى المشيقح', 'role_type' => 'admin', 'is_active' => true, 'email_verified_at' => now()]);
        $admin->assignRole(RbacCatalog::ROLE_ADMIN);
        $running = $this->program(['title' => 'برنامج جارٍ', 'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDays(2)->toDateString()]);
        $this->registration($running, RegistrationStatus::Approved);
        $this->registration($running, RegistrationStatus::Pending);
        $link = app(ProgramAttendanceLinkService::class)->create($running, 'اليوم الثاني', 30);
        app(ProgramAttendanceLinkService::class)->open($link);
        $this->program(['title' => 'برنامج قادم', 'start_date' => now()->addDays(5)->toDateString(), 'end_date' => now()->addDays(6)->toDateString()]);
        app(StaffAlerts::class)->refresh();

        $this->actingAsOtpVerified($admin->fresh())
            ->get('/admin')
            ->assertOk()
            ->assertSee('مرحباً، لمى')
            ->assertSee('إحصاءات المنصة')
            ->assertSee('تسجيلات في البرامج')
            ->assertSee('الشهر الماضي')
            ->assertSee('البرامج الجارية والقادمة')
            ->assertSee('برنامج جارٍ')
            ->assertSee('مفتوح · 0 حاضر')
            ->assertSee('برنامج قادم')
            ->assertSee('يبدأ بعد 5 أيام')
            ->assertSee('ملخص التنبيهات')
            ->assertSee('1 طلب بانتظار القبول')
            ->assertSee('في برنامج واحد')
            ->assertSee('التحضير مفتوح الآن')
            ->assertDontSee('قيد البناء');
    }

    public function test_empty_dashboard_says_nothing_is_needed(): void
    {
        $admin = User::factory()->create(['role_type' => 'admin', 'is_active' => true, 'email_verified_at' => now()]);
        $admin->assignRole(RbacCatalog::ROLE_ADMIN);

        $this->actingAsOtpVerified($admin->fresh())
            ->get('/admin')
            ->assertOk()
            ->assertSee('لا يوجد ما يتطلب إجراءً منك الآن.')
            ->assertSee('لا توجد برامج جارية أو قادمة.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function program(array $overrides = []): TrainingProgram
    {
        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج',
            'slug' => 'db-'.uniqid(),
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Professional,
            'delivery_mode' => ProgramDeliveryMode::Remote,
            'status' => ProgramStatus::Published,
            'published_at' => now(),
        ], $overrides));
    }

    private function registration(TrainingProgram $program, RegistrationStatus $status): ProgramRegistration
    {
        $user = User::factory()->create(['role_type' => 'beneficiary', 'is_active' => true]);

        return ProgramRegistration::query()->create(['training_program_id' => $program->id, 'user_id' => $user->id, 'status' => $status]);
    }
}
