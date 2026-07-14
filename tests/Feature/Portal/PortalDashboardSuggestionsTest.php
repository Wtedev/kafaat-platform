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

class PortalDashboardSuggestionsTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
    }

    public function test_suggestions_exclude_registered_programs_and_opportunities(): void
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('beneficiary');

        TrainingProgram::query()->create([
            'title' => 'برنامج مقترح',
            'slug' => 'suggested-prog-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
            'registration_start' => now()->subDay(),
            'registration_end' => now()->addMonth(),
            'start_date' => now()->addWeek(),
            'end_date' => now()->addMonths(2),
        ]);

        $registeredProgram = TrainingProgram::query()->create([
            'title' => 'برنامج مسجّل',
            'slug' => 'registered-prog-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
            'registration_start' => now()->subDay(),
            'registration_end' => now()->addMonth(),
            'start_date' => now()->addWeek(),
            'end_date' => now()->addMonths(2),
        ]);

        ProgramRegistration::query()->create([
            'user_id' => $user->id,
            'training_program_id' => $registeredProgram->id,
            'status' => RegistrationStatus::Approved,
        ]);

        VolunteerOpportunity::query()->create([
            'title' => 'فرصة مقترحة',
            'slug' => 'suggested-vol-'.uniqid(),
            'status' => OpportunityStatus::Published,
            'published_at' => now(),
            'start_date' => now()->addDays(3),
            'hours_expected' => 10,
        ]);

        $registeredOpp = VolunteerOpportunity::query()->create([
            'title' => 'فرصة مسجّلة',
            'slug' => 'registered-vol-'.uniqid(),
            'status' => OpportunityStatus::Published,
            'published_at' => now(),
            'start_date' => now()->addDays(5),
            'hours_expected' => 8,
        ]);

        VolunteerRegistration::query()->create([
            'user_id' => $user->id,
            'opportunity_id' => $registeredOpp->id,
            'status' => RegistrationStatus::Pending,
        ]);

        $composed = PortalDashboardComposer::compose($user);

        $suggestedProgramTitles = $composed['suggestedPrograms']->pluck('title');
        $this->assertTrue($suggestedProgramTitles->contains('برنامج مقترح'));
        $this->assertFalse($suggestedProgramTitles->contains('برنامج مسجّل'));

        $suggestedOppTitles = $composed['suggestedOpportunities']->pluck('title');
        $this->assertTrue($suggestedOppTitles->contains('فرصة مقترحة'));
        $this->assertFalse($suggestedOppTitles->contains('فرصة مسجّلة'));

        $registeredOppTitles = $composed['volunteerRows']->pluck('title');
        $this->assertTrue($registeredOppTitles->contains('فرصة مسجّلة'));
        $this->assertFalse($registeredOppTitles->contains('فرصة مقترحة'));

        $this->assertTrue(
            $composed['activities']->every(fn (array $row): bool => empty($row['discover']))
        );
    }

    public function test_dashboard_renders_suggested_section_heading(): void
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('beneficiary');

        TrainingProgram::query()->create([
            'title' => 'برنامج ظاهر',
            'slug' => 'dash-visible-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
            'start_date' => now()->addWeek(),
            'end_date' => now()->addMonths(2),
        ]);

        $this->actingAsOtpVerified($user)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('برامج كفاءات مقترحة لك', false)
            ->assertSee('برنامج ظاهر', false)
            ->assertSee('تعلّمي الحالي', false);
    }
}
