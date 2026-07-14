<?php

namespace Tests\Feature\Portal;

use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Portal\PortalDashboardComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class PortalProgramAttendanceTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
    }

    public function test_portal_program_show_redirects_to_public_program_page(): void
    {
        [$user, $program] = $this->registeredBeneficiaryWithProgram();

        $this->actingAsOtpVerified($user)
            ->get(route('portal.programs.show', $program))
            ->assertRedirect(route('public.programs.show', $program->slug));
    }

    public function test_portal_program_show_attendance_query_redirects_to_programs_list_with_modal(): void
    {
        [$user, $program] = $this->registeredBeneficiaryWithProgram();

        $this->actingAsOtpVerified($user)
            ->get(route('portal.programs.show', ['trainingProgram' => $program, 'attendance' => 1]))
            ->assertRedirect(route('portal.programs', ['open_attendance' => $program->id]));
    }

    public function test_programs_list_renders_qr_attendance_modal_for_in_person_program(): void
    {
        [$user, $program] = $this->registeredBeneficiaryWithProgram(ProgramDeliveryMode::InPerson);

        $this->actingAsOtpVerified($user)
            ->get(route('portal.programs'))
            ->assertOk()
            ->assertSee('id="program-attendance-qr-'.$program->id.'"', false)
            ->assertSee('portal-attendance-open', false)
            ->assertSee('QR الحضور', false)
            ->assertDontSee('نبذة عن البرنامج', false);
    }

    public function test_dashboard_program_activity_links_to_public_program_page(): void
    {
        [$user, $program] = $this->registeredBeneficiaryWithProgram();

        $composed = PortalDashboardComposer::compose($user);
        $activity = $composed['activities']->first();

        $this->assertNotNull($activity);
        $this->assertSame(route('public.programs.show', $program->slug), $activity['cta_url']);
    }

    /**
     * @return array{0: User, 1: TrainingProgram}
     */
    private function registeredBeneficiaryWithProgram(
        ProgramDeliveryMode $deliveryMode = ProgramDeliveryMode::Remote,
    ): array {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
            'notification_prefs_set_at' => now(),
        ]);
        $user->assignRole('beneficiary');

        $program = TrainingProgram::query()->create([
            'title' => 'برنامج تجريبي',
            'slug' => 'test-program-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
            'delivery_mode' => $deliveryMode,
        ]);

        ProgramRegistration::query()->create([
            'user_id' => $user->id,
            'training_program_id' => $program->id,
            'status' => RegistrationStatus::Approved,
        ]);

        return [$user, $program];
    }
}
