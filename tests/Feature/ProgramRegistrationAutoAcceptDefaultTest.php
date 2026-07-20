<?php

namespace Tests\Feature;

use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\ProgramCapacityExceededException;
use App\Exceptions\RegistrationWindowClosedException;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class ProgramRegistrationAutoAcceptDefaultTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
    }

    public function test_program_created_without_explicit_auto_accept_defaults_to_false(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج بدون قبول تلقائي',
            'slug' => 'auto-accept-default-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
        ]);

        $this->assertFalse($program->auto_accept_registrations);
        $this->assertFalse((bool) $program->fresh()->auto_accept_registrations);
    }

    public function test_registration_is_pending_when_program_has_no_explicit_auto_accept(): void
    {
        $user = $this->makeBeneficiary();
        $program = $this->makeOpenProgram();

        $registration = app(ProgramRegistrationService::class)->register($program, $user);

        $this->assertSame(RegistrationStatus::Pending, $registration->status);
        $this->assertNull($registration->approved_by);
        $this->assertNull($registration->approved_at);
    }

    public function test_registration_is_pending_when_auto_accept_is_false(): void
    {
        $user = $this->makeBeneficiary();
        $program = $this->makeOpenProgram([
            'auto_accept_registrations' => false,
        ]);

        $registration = app(ProgramRegistrationService::class)->register($program, $user);

        $this->assertSame(RegistrationStatus::Pending, $registration->status);
        $this->assertNull($registration->approved_by);
        $this->assertNull($registration->approved_at);
    }

    public function test_registration_is_approved_when_auto_accept_is_true(): void
    {
        $owner = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $owner->assignRole('staff');

        $user = $this->makeBeneficiary();
        $program = $this->makeOpenProgram([
            'auto_accept_registrations' => true,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
        ]);

        $registration = app(ProgramRegistrationService::class)->register($program, $user);

        $this->assertSame(RegistrationStatus::Approved, $registration->status);
        $this->assertSame($owner->id, $registration->approved_by);
        $this->assertNotNull($registration->approved_at);
    }

    public function test_auto_accept_does_not_approve_when_capacity_is_full(): void
    {
        $owner = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $owner->assignRole('staff');

        $existing = $this->makeBeneficiary();
        $applicant = $this->makeBeneficiary();

        $program = $this->makeOpenProgram([
            'auto_accept_registrations' => true,
            'capacity' => 1,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
        ]);

        ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $existing->id,
            'status' => RegistrationStatus::Approved,
            'approved_by' => $owner->id,
            'approved_at' => now(),
        ]);

        try {
            app(ProgramRegistrationService::class)->register($program, $applicant);
            $this->fail('Expected ProgramCapacityExceededException was not thrown.');
        } catch (ProgramCapacityExceededException) {
            // expected
        }

        $registration = ProgramRegistration::query()
            ->where('training_program_id', $program->id)
            ->where('user_id', $applicant->id)
            ->first();

        $this->assertNotNull($registration);
        $this->assertSame(RegistrationStatus::Pending, $registration->status);
        $this->assertNull($registration->approved_by);
        $this->assertNull($registration->approved_at);
    }

    public function test_auto_accept_does_not_register_when_registration_window_is_closed(): void
    {
        $user = $this->makeBeneficiary();
        $program = $this->makeOpenProgram([
            'auto_accept_registrations' => true,
            'registration_start' => now()->subMonth()->toDateString(),
            'registration_end' => now()->subDay()->toDateString(),
        ]);

        try {
            app(ProgramRegistrationService::class)->register($program, $user);
            $this->fail('Expected RegistrationWindowClosedException was not thrown.');
        } catch (RegistrationWindowClosedException) {
            // expected
        }

        $this->assertDatabaseMissing('program_registrations', [
            'training_program_id' => $program->id,
            'user_id' => $user->id,
        ]);
    }

    private function makeBeneficiary(): User
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('beneficiary');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOpenProgram(array $overrides = []): TrainingProgram
    {
        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج قبول تلقائي',
            'slug' => 'auto-accept-reg-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
            'registration_start' => now()->subDay()->toDateString(),
            'registration_end' => now()->addMonth()->toDateString(),
        ], $overrides));
    }
}
