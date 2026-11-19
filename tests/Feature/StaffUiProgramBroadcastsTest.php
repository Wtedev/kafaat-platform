<?php

namespace Tests\Feature;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramBroadcastStatus;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Jobs\DispatchProgramBroadcastChunksJob;
use App\Models\ProgramBroadcast;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Attendance\ProgramAttendanceLinkService;
use App\Services\Rbac\RbacCatalog;
use App\Services\Surveys\ProgramSurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiProgramBroadcastsTest extends TestCase
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

    public function test_tab_offers_segments_with_counts_and_saves_a_draft(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $this->registration($program, RegistrationStatus::Approved);
        $this->registration($program, RegistrationStatus::Approved);
        $this->registration($program, RegistrationStatus::Pending);

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.tab', [$program, 'broadcasts']).'?compose=1')
            ->assertOk()
            ->assertSee('المقبولون (2)')
            ->assertSee('بانتظار المراجعة (1)')
            ->assertSee('كل المسجّلين (3)');

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.broadcasts.store', $program), [
                'segment' => 'approved', 'subject' => 'تذكير بموعد البرنامج', 'content' => "نذكّركم بموعد البرنامج غداً.\nالساعة 9 صباحاً.", 'intent' => 'draft',
            ])
            ->assertSessionHas('status');

        $draft = ProgramBroadcast::query()->sole();
        $this->assertSame(ProgramBroadcastStatus::Draft, $draft->status);
        $this->assertSame(['approved', 'completed'], $draft->audience_statuses);

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.tab', [$program, 'broadcasts']).'?edit='.$draft->id)
            ->assertSee('تعديل المسودة')
            ->assertSee('تذكير بموعد البرنامج');

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.broadcasts.preview', $program), ['subject' => 'عنوان', 'content' => 'نص المعاينة'])
            ->assertOk()
            ->assertSee('نص المعاينة')
            ->assertSee('اسم المتدرب');
    }

    public function test_sending_to_trainees_who_skipped_a_survey_or_missed_a_day(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $program = $this->program();
        $answered = $this->registration($program, RegistrationStatus::Approved);
        $skipped = $this->registration($program, RegistrationStatus::Approved);
        $this->registration($program, RegistrationStatus::Pending);
        app(ProgramSurveyService::class)->provisionFromScratch($program);
        $post = $program->surveys()->where('type', 'post')->sole();
        DB::table('survey_responses')->insert(['id' => (string) Str::uuid(), 'program_survey_id' => $post->id, 'registration_id' => $answered->id, 'created_at' => now(), 'updated_at' => now()]);
        $links = app(ProgramAttendanceLinkService::class);
        $day = $links->create($program, 'اليوم الأول', 10);
        $links->markManual($day, $skipped);

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.tab', [$program, 'broadcasts']).'?compose=1')
            ->assertSee('المقبولون اللي ما عبّوا الاستبيان بعدي (1)')
            ->assertSee('المقبولون الغائبون عن «اليوم الأول» (1)');

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.broadcasts.store', $program), [
                'segment' => 'survey:post', 'subject' => 'باقي الاستبيان البعدي', 'content' => 'نرجو تعبئة الاستبيان.', 'intent' => 'send',
            ])
            ->assertSessionHas('status');

        $broadcast = ProgramBroadcast::query()->sole();
        $this->assertSame(['survey_missing' => 'post'], $broadcast->audience_filter);
        $this->assertSame(ProgramBroadcastStatus::Queued, $broadcast->status);
        $this->assertSame([$skipped->id], $broadcast->recipients()->pluck('program_registration_id')->all());
        Queue::assertPushed(DispatchProgramBroadcastChunksJob::class);

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.tab', [$program, 'broadcasts']))
            ->assertSee('المقبولون اللي ما عبّوا الاستبيان بعدي')
            ->assertSee('في الانتظار');

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.broadcasts.store', $program), [
                'segment' => 'absent:'.$day->id, 'subject' => 'غبت', 'content' => 'نفتقدك.', 'intent' => 'send',
            ]);
        $absent = ProgramBroadcast::query()->latest('id')->first();
        $this->assertSame([$answered->id], $absent->recipients()->pluck('program_registration_id')->all());

        // Copy makes a new draft with the same audience.
        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.broadcasts.action', [$program, $broadcast]), ['action' => 'copy']);
        $copy = ProgramBroadcast::query()->latest('id')->first();
        $this->assertSame(ProgramBroadcastStatus::Draft, $copy->status);
        $this->assertSame(['survey_missing' => 'post'], $copy->audience_filter);
    }

    public function test_empty_audience_is_refused_and_drafts_can_be_deleted(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $program = $this->program();

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.broadcasts.store', $program), [
                'segment' => 'approved', 'subject' => 'لا أحد', 'content' => 'نص', 'intent' => 'send',
            ])
            ->assertSessionHasErrors('broadcast');
        $draft = ProgramBroadcast::query()->sole();
        $this->assertSame(ProgramBroadcastStatus::Draft, $draft->status);

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.broadcasts.action', [$program, $draft]), ['action' => 'delete']);
        $this->assertSame(0, ProgramBroadcast::query()->count());

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.broadcasts.store', $program), ['segment' => 'nope', 'subject' => 'x', 'content' => 'y'])
            ->assertSessionHasErrors('segment');
    }

    public function test_sending_needs_the_email_permission(): void
    {
        $program = $this->program();
        $viewer = $this->staff(['programs.view']);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.tab', [$program, 'broadcasts']))
            ->assertOk()
            ->assertDontSee('رسالة جديدة');

        $this->actingAsOtpVerified($viewer)
            ->post(route('staff-ui.programs.broadcasts.store', $program), ['segment' => 'approved', 'subject' => 'x', 'content' => 'y'])
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function program(array $overrides = []): TrainingProgram
    {
        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج الرسائل',
            'slug' => 'bc-'.uniqid(),
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Professional,
            'delivery_mode' => ProgramDeliveryMode::Remote,
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
        ], $overrides));
    }

    private function registration(TrainingProgram $program, RegistrationStatus $status): ProgramRegistration
    {
        $user = User::factory()->create(['role_type' => 'beneficiary', 'is_active' => true]);

        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => $status,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role_type' => 'admin', 'is_active' => true, 'email_verified_at' => now()]);
        $admin->assignRole(RbacCatalog::ROLE_ADMIN);

        return $admin->fresh();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function staff(array $permissions): User
    {
        $staff = User::factory()->create(['role_type' => 'staff', 'is_active' => true, 'email_verified_at' => now()]);
        $staff->assignRole(RbacCatalog::ROLE_STAFF);
        $staff->givePermissionTo($permissions);

        return $staff->fresh();
    }
}
