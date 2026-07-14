<?php

namespace Tests\Feature;

use App\Enums\OpportunityStatus;
use App\Enums\ProgramStatus;
use App\Models\Profile;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class MobilePrimaryCtaVisibilityTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
    }

    public function test_program_show_renders_mobile_sticky_registration_cta(): void
    {
        $user = $this->makePortalUser();
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج جوال',
            'slug' => 'mobile-program-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
        ]);

        $this->actingAsOtpVerified($user)
            ->get(route('public.programs.show', $program))
            ->assertOk()
            ->assertSee('program-register-form', false)
            ->assertSee('form="program-register-form"', false)
            ->assertSee('سجّل في البرنامج', false);
    }

    public function test_volunteer_show_renders_mobile_sticky_apply_cta(): void
    {
        $user = $this->makePortalUser();
        $opportunity = VolunteerOpportunity::query()->create([
            'title' => 'فرصة جوال',
            'slug' => 'mobile-volunteer-'.uniqid(),
            'status' => OpportunityStatus::Published,
            'published_at' => now(),
        ]);

        $this->actingAsOtpVerified($user)
            ->get(route('public.volunteering.show', $opportunity))
            ->assertOk()
            ->assertSee('volunteer-register-form', false)
            ->assertSee('form="volunteer-register-form"', false)
            ->assertSee('قدّم طلبك', false);
    }

    public function test_portal_profile_edit_renders_mobile_save_bar(): void
    {
        $user = $this->makePortalUser();

        $this->actingAsOtpVerified($user)
            ->get(route('portal.settings.profile'))
            ->assertOk()
            ->assertSee('portal-profile-form', false)
            ->assertSee('form="portal-profile-form"', false)
            ->assertSee('حفظ التعديلات', false);
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
}
