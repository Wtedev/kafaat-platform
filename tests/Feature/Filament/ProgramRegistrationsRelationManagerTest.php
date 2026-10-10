<?php

namespace Tests\Feature\Filament;

use App\Enums\CompetencyTrack;
use App\Enums\IdentityCategory;
use App\Enums\IdentityType;
use App\Enums\ProfileGender;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Filament\Resources\TrainingProgramResource\Pages\ViewTrainingProgram;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramRegistrationsRelationManager;
use App\Jobs\BulkApproveProgramRegistrationsJob;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Identity\IdentityNumberService;
use App\Support\ArabicText;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class ProgramRegistrationsRelationManagerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00', 'Asia/Riyadh'));
    }

    public function test_status_filter_labels_are_arabic_and_filters_apply_immediately(): void
    {
        $admin = $this->admin();
        $program = $this->program($admin);
        $pending = $this->registration($program, 'أحمد', RegistrationStatus::Pending);
        $rejected = $this->registration($program, 'سارة', RegistrationStatus::Rejected);

        $this->assertSame('قيد الانتظار', RegistrationStatus::Pending->getLabel());
        $this->assertSame('مرفوض', RegistrationStatus::Rejected->getLabel());
        $this->assertSame('مقبول', RegistrationStatus::Approved->getLabel());

        $this->withSession(['otp_verified' => true]);

        $component = Livewire::actingAs($admin)
            ->test(ProgramRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ]);

        $this->assertFalse($component->instance()->getTable()->hasDeferredFilters());
        $component
            ->assertTableColumnDoesNotExist('certificate_eligibility')
            ->assertSee('عدد النتائج:')
            ->assertCanSeeTableRecords([$pending, $rejected])
            ->filterTable('status', RegistrationStatus::Pending->value)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$rejected]);
    }

    public function test_gender_and_age_filters(): void
    {
        $admin = $this->admin();
        $program = $this->program($admin);

        $maleYoung = $this->registration($program, 'شاب', RegistrationStatus::Pending, [
            'gender' => ProfileGender::Male,
            'birth_date' => now()->subYears(20)->toDateString(),
        ]);
        $femaleOlder = $this->registration($program, 'كبيرة', RegistrationStatus::Pending, [
            'gender' => ProfileGender::Female,
            'birth_date' => now()->subYears(40)->toDateString(),
        ]);
        $unspecified = $this->registration($program, 'غير محدد', RegistrationStatus::Pending, [
            'gender' => null,
            'birth_date' => null,
        ]);

        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($admin)
            ->test(ProgramRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->assertTableFilterExists('gender')
            ->assertTableFilterExists('age')
            ->filterTable('gender', 'male')
            ->assertCanSeeTableRecords([$maleYoung])
            ->assertCanNotSeeTableRecords([$femaleOlder, $unspecified])
            ->filterTable('gender', 'unspecified')
            ->assertCanSeeTableRecords([$unspecified])
            ->assertCanNotSeeTableRecords([$maleYoung, $femaleOlder]);

        Livewire::actingAs($admin)
            ->test(ProgramRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->filterTable('age', ['preset' => '18_24'])
            ->assertCanSeeTableRecords([$maleYoung])
            ->assertCanNotSeeTableRecords([$femaleOlder, $unspecified])
            ->filterTable('age', ['preset' => '35_plus'])
            ->assertCanSeeTableRecords([$femaleOlder])
            ->assertCanNotSeeTableRecords([$maleYoung])
            ->filterTable('age', ['preset' => 'unspecified'])
            ->assertCanSeeTableRecords([$unspecified]);
    }

    public function test_nationality_filter_splits_saudi_and_non_saudi_by_identity_category(): void
    {
        $admin = $this->admin();
        $program = $this->program($admin);
        $saudi = $this->registration($program, 'سعودي', RegistrationStatus::Pending);
        $saudi->user->forceFill([
            'identity_category' => IdentityCategory::Saudi,
            'identity_type' => IdentityType::NationalId,
        ])->save();
        $resident = $this->registration($program, 'مقيم', RegistrationStatus::Pending);
        $resident->user->forceFill([
            'identity_category' => IdentityCategory::Resident,
            'identity_type' => IdentityType::Iqama,
        ])->save();
        $unspecified = $this->registration($program, 'بدون هوية', RegistrationStatus::Pending);
        $unspecified->user->forceFill([
            'identity_category' => null,
            'identity_type' => null,
        ])->save();

        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($admin)
            ->test(ProgramRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->assertTableFilterExists('nationality')
            ->assertTableColumnExists('user.identity_category')
            ->filterTable('nationality', 'saudi')
            ->assertCanSeeTableRecords([$saudi])
            ->assertCanNotSeeTableRecords([$resident, $unspecified])
            ->filterTable('nationality', 'non_saudi')
            ->assertCanSeeTableRecords([$resident])
            ->assertCanNotSeeTableRecords([$saudi, $unspecified])
            ->filterTable('nationality', 'unspecified')
            ->assertCanSeeTableRecords([$unspecified])
            ->assertCanNotSeeTableRecords([$saudi, $resident]);
    }

    public function test_table_shows_only_the_first_four_identity_digits(): void
    {
        $admin = $this->admin();
        $program = $this->program($admin);
        $registration = $this->registration($program, 'مستفيد', RegistrationStatus::Pending);
        $registration->user->forceFill(
            IdentityNumberService::prepareStoragePayload('2345678901', IdentityType::Iqama)
        )->save();

        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($admin)
            ->test(ProgramRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->assertTableColumnExists('identity_first_four')
            ->assertSee('2345')
            ->assertDontSee('2345678901');
    }

    public function test_search_covers_four_names_with_arabic_folding(): void
    {
        $admin = $this->admin();
        $program = $this->program($admin);

        $match = $this->registration($program, 'أحمد علي', RegistrationStatus::Pending, userAttributes: [
            'first_name' => 'أحمد',
            'father_name' => 'محمد',
            'grandfather_name' => 'عبدالله',
            'family_name' => 'العتيبي',
            'name' => 'أحمد محمد عبدالله العتيبي',
        ]);
        $other = $this->registration($program, 'خالد', RegistrationStatus::Pending, userAttributes: [
            'first_name' => 'خالد',
            'family_name' => 'الزهراني',
            'name' => 'خالد الزهراني',
        ]);

        $this->assertSame('احمد', ArabicText::fold('أحمد'));

        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($admin)
            ->test(ProgramRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->searchTable('احمد')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('العتيبي')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_search_by_identity_number_shows_the_registrant_name(): void
    {
        $admin = $this->admin();
        $program = $this->program($admin);
        $match = $this->registration($program, 'نورة سعد', RegistrationStatus::Approved, userAttributes: [
            'first_name' => 'نورة',
            'family_name' => 'سعد',
            'name' => 'نورة سعد',
        ]);
        $other = $this->registration($program, 'خالد الزهراني', RegistrationStatus::Approved);
        $match->user->forceFill(IdentityNumberService::prepareStoragePayload('1123456789', IdentityType::NationalId))->save();

        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($admin)
            ->test(ProgramRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->searchTable('1123456789')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other])
            ->assertSee('نورة سعد');
    }

    public function test_select_all_is_not_limited_to_current_page_and_bulk_actions_exist(): void
    {
        $admin = $this->admin();
        $program = $this->program($admin);
        $this->registration($program, 'أ', RegistrationStatus::Pending);

        $this->withSession(['otp_verified' => true]);

        $component = Livewire::actingAs($admin)
            ->test(ProgramRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ]);

        $this->assertFalse($component->instance()->getTable()->selectsCurrentPageOnly());
        $component
            ->assertTableBulkActionExists('bulkApprove')
            ->assertTableBulkActionExists('bulkReject')
            ->assertTableBulkActionExists('bulkExport');
    }

    public function test_bulk_approve_from_table_and_queues_when_over_threshold(): void
    {
        $admin = $this->admin();
        $program = $this->program($admin);
        $a = $this->registration($program, 'قبول أ', RegistrationStatus::Pending);
        $b = $this->registration($program, 'قبول ب', RegistrationStatus::Pending);

        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($admin)
            ->test(ProgramRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->callTableBulkAction('bulkApprove', [$a, $b]);

        $this->assertSame(RegistrationStatus::Approved, $a->fresh()->status);
        $this->assertSame(RegistrationStatus::Approved, $b->fresh()->status);
        $this->assertSame(1, AuditLog::query()->where('action', 'program_registrations.bulk_approve')->count());

        Queue::fake();
        $many = collect(range(1, 51))->map(
            fn (int $i) => $this->registration($program, "ق{$i}", RegistrationStatus::Pending),
        );

        Livewire::actingAs($admin)
            ->test(ProgramRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->callTableBulkAction('bulkApprove', $many->all());

        Queue::assertPushed(BulkApproveProgramRegistrationsJob::class, function (BulkApproveProgramRegistrationsJob $job) use ($admin, $program): bool {
            return $job->actorId === $admin->id
                && $job->programId === $program->id
                && count($job->registrationIds) === 51;
        });
    }

    public function test_bulk_reject_from_table(): void
    {
        $admin = $this->admin();
        $program = $this->program($admin);
        $pending = $this->registration($program, 'للرفض', RegistrationStatus::Pending);

        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($admin)
            ->test(ProgramRegistrationsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->callTableBulkAction('bulkReject', [$pending], data: [
                'rejected_reason' => 'غير مستوفٍ',
            ]);

        $this->assertSame(RegistrationStatus::Rejected, $pending->fresh()->status);
        $this->assertSame('غير مستوفٍ', $pending->fresh()->rejected_reason);
        $this->assertSame(1, AuditLog::query()->where('action', 'program_registrations.bulk_reject')->count());
    }

    private function admin(): User
    {
        $admin = User::factory()->create([
            'role_type' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    private function program(User $owner): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => 'برنامج المسجلين',
            'slug' => 'filament-regs-'.uniqid(),
            'description' => 'وصف',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Self,
            'delivery_mode' => ProgramDeliveryMode::Hybrid,
            'status' => ProgramStatus::Published,
            'published_at' => now()->subDay(),
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'capacity' => 200,
            'auto_accept_registrations' => false,
        ]);
    }

    /**
     * @param  array{gender?: ?ProfileGender, birth_date?: ?string}  $profile
     * @param  array<string, mixed>  $userAttributes
     */
    private function registration(
        TrainingProgram $program,
        string $name,
        RegistrationStatus $status,
        array $profile = [],
        array $userAttributes = [],
    ): ProgramRegistration {
        $user = User::factory()->create(array_merge([
            'name' => $name,
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $userAttributes));

        if ($profile !== [] || ! Profile::query()->where('user_id', $user->id)->exists()) {
            Profile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'gender' => $profile['gender'] ?? null,
                    'birth_date' => $profile['birth_date'] ?? null,
                    'membership_type' => 'beneficiary',
                ],
            );
        }

        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => $status,
            'approved_at' => $status === RegistrationStatus::Approved ? now() : null,
        ]);
    }
}
