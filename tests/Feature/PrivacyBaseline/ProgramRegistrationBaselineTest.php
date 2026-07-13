<?php

namespace Tests\Feature\PrivacyBaseline;

use App\Enums\OpportunityStatus;
use App\Enums\PathStatus;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Models\LearningPath;
use App\Models\ProgramRegistration;
use App\Models\Profile;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Services\PathRegistrationService;
use App\Services\ProgramRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class ProgramRegistrationBaselineTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
    }

    public function test_portal_user_can_register_for_standalone_program(): void
    {
        $user = $this->makePortalUser();
        $program = $this->makeOpenProgram();

        $this->actingAsOtpVerified($user)
            ->post(route('public.programs.register', $program))
            ->assertRedirect();

        $this->assertDatabaseHas('program_registrations', [
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Pending->value,
        ]);
    }

    public function test_program_registration_does_not_duplicate_for_same_user(): void
    {
        $user = $this->makePortalUser();
        $program = $this->makeOpenProgram();

        app(ProgramRegistrationService::class)->register($program, $user);
        app(ProgramRegistrationService::class)->register($program, $user);

        $this->assertSame(
            1,
            ProgramRegistration::query()
                ->where('training_program_id', $program->id)
                ->where('user_id', $user->id)
                ->count()
        );
    }

    public function test_staff_user_can_register_via_public_program_route(): void
    {
        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole('staff');
        $program = $this->makeOpenProgram();

        $this->actingAsOtpVerified($staff)
            ->post(route('public.programs.register', $program))
            ->assertRedirect();

        $this->assertDatabaseHas('program_registrations', [
            'training_program_id' => $program->id,
            'user_id' => $staff->id,
        ]);
    }

    public function test_portal_user_can_register_for_learning_path(): void
    {
        $user = $this->makePortalUser();
        $path = LearningPath::query()->create([
            'title' => 'مسار تجريبي',
            'slug' => 'baseline-path',
            'status' => PathStatus::Published,
            'published_at' => now(),
        ]);

        app(PathRegistrationService::class)->register($user, $path);

        $this->assertDatabaseHas('path_registrations', [
            'learning_path_id' => $path->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_portal_user_can_register_for_volunteer_opportunity(): void
    {
        $user = $this->makePortalUser();
        $opportunity = VolunteerOpportunity::query()->create([
            'title' => 'فرصة تطوع',
            'slug' => 'baseline-volunteer',
            'status' => OpportunityStatus::Published,
            'published_at' => now(),
        ]);

        $this->actingAsOtpVerified($user)
            ->post(route('public.volunteering.register', $opportunity))
            ->assertRedirect();

        $this->assertDatabaseHas('volunteer_registrations', [
            'opportunity_id' => $opportunity->id,
            'user_id' => $user->id,
        ]);
    }

    private function makePortalUser(): User
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('beneficiary');
        Profile::query()->create(['user_id' => $user->id]);

        return $user;
    }

    private function makeOpenProgram(): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => 'برنامج baseline',
            'slug' => 'baseline-program-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
        ]);
    }
}
