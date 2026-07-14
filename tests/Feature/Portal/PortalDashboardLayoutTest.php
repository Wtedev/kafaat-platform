<?php

namespace Tests\Feature\Portal;

use App\Enums\OpportunityStatus;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
use App\Services\Portal\PortalDashboardComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class PortalDashboardLayoutTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
    }

    public function test_composer_returns_only_registered_current_activity(): void
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('beneficiary');

        $registeredProgram = TrainingProgram::query()->create([
            'title' => 'برنامج مسجّل',
            'slug' => 'registered-prog-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
        ]);

        TrainingProgram::query()->create([
            'title' => 'برنامج غير مسجّل',
            'slug' => 'other-prog-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
        ]);

        ProgramRegistration::query()->create([
            'user_id' => $user->id,
            'training_program_id' => $registeredProgram->id,
            'status' => RegistrationStatus::Approved,
        ]);

        VolunteerOpportunity::query()->create([
            'title' => 'فرصة غير مسجّلة',
            'slug' => 'open-vol-'.uniqid(),
            'status' => OpportunityStatus::Published,
            'published_at' => now(),
        ]);

        $registeredOpp = VolunteerOpportunity::query()->create([
            'title' => 'فرصة مسجّلة',
            'slug' => 'registered-vol-'.uniqid(),
            'status' => OpportunityStatus::Published,
            'published_at' => now(),
        ]);

        VolunteerRegistration::query()->create([
            'user_id' => $user->id,
            'opportunity_id' => $registeredOpp->id,
            'status' => RegistrationStatus::Pending,
        ]);

        $composed = PortalDashboardComposer::compose($user);

        $this->assertArrayNotHasKey('suggestedPrograms', $composed);
        $this->assertArrayNotHasKey('suggestedOpportunities', $composed);

        $this->assertTrue($composed['activities']->pluck('title')->contains('برنامج مسجّل'));
        $this->assertFalse($composed['activities']->pluck('title')->contains('برنامج غير مسجّل'));
        $this->assertTrue($composed['volunteerRows']->pluck('title')->contains('فرصة مسجّلة'));
        $this->assertFalse($composed['volunteerRows']->pluck('title')->contains('فرصة غير مسجّلة'));
    }

    public function test_dashboard_uses_sidebar_notifications_and_current_activity(): void
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('beneficiary');

        $this->actingAsOtpVerified($user)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('نشاطي الحالي', false)
            ->assertSee('التنبيهات', false)
            ->assertDontSee('برامج كفاءات مقترحة لك', false);
    }
}
