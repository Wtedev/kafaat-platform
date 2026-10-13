<?php

namespace Tests\Feature\Volunteering;

use App\Enums\OpportunityStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\DuplicateRegistrationException;
use App\Exceptions\OpportunityCapacityExceededException;
use App\Exceptions\OpportunityNotPublishedException;
use App\Exceptions\RegistrationRejectedException;
use App\Filament\Resources\VolunteerOpportunityResource\Pages\ViewVolunteerOpportunity;
use App\Filament\Resources\VolunteerOpportunityResource\RelationManagers\RegistrationsRelationManager;
use App\Models\Profile;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
use App\Services\VolunteerRegistrationService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

/**
 * اختبارات خدمة التسجيل في الفرص التطوعية (VolunteerRegistrationService)
 * واختبار زر القبول/الرفض داخل تبويب "التسجيلات" في لوحة الإدارة.
 */
class VolunteerRegistrationServiceTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    private VolunteerRegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        Notification::fake();

        $this->service = app(VolunteerRegistrationService::class);
    }

    // ─── التسجيل (register) ──────────────────────────────────────────────────

    public function test_register_creates_pending_registration_for_published_opportunity(): void
    {
        $user = $this->makeBeneficiary();
        $opportunity = $this->makeOpportunity();

        $registration = $this->service->register($user, $opportunity);

        $this->assertSame(RegistrationStatus::Pending, $registration->status);
        $this->assertDatabaseHas('volunteer_registrations', [
            'opportunity_id' => $opportunity->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Pending->value,
        ]);
    }

    public function test_register_is_rejected_for_draft_opportunity(): void
    {
        $user = $this->makeBeneficiary();
        $opportunity = $this->makeOpportunity(['status' => OpportunityStatus::Draft]);

        $this->expectException(OpportunityNotPublishedException::class);

        $this->service->register($user, $opportunity);
    }

    public function test_register_is_rejected_for_archived_opportunity(): void
    {
        $user = $this->makeBeneficiary();
        $opportunity = $this->makeOpportunity(['status' => OpportunityStatus::Archived]);

        $this->expectException(OpportunityNotPublishedException::class);

        $this->service->register($user, $opportunity);
    }

    public function test_register_twice_throws_duplicate_exception(): void
    {
        $user = $this->makeBeneficiary();
        $opportunity = $this->makeOpportunity();

        $this->service->register($user, $opportunity);

        $this->expectException(DuplicateRegistrationException::class);

        $this->service->register($user, $opportunity);
    }

    /**
     * قرار المشرفة: المتطوع المرفوض لا يمكنه إعادة التقديم على نفس الفرصة.
     */
    public function test_rejected_volunteer_cannot_register_again(): void
    {
        $user = $this->makeBeneficiary();
        $opportunity = $this->makeOpportunity();
        $admin = $this->makeAdmin();

        $first = $this->service->register($user, $opportunity);
        $this->service->reject($first, $admin, 'غير مناسب');

        try {
            $this->service->register($user, $opportunity);
            $this->fail('كان يجب أن يرمي استثناء التسجيل المرفوض.');
        } catch (RegistrationRejectedException) {
            $this->assertSame(RegistrationStatus::Rejected, $first->fresh()->status);
            $this->assertSame(1, VolunteerRegistration::query()
                ->where('opportunity_id', $opportunity->id)
                ->where('user_id', $user->id)
                ->count());
        }
    }

    /**
     * التسجيل الملغي يُعاد فتحه كـ"قيد الانتظار" (قيد unique يمنع صفاً ثانياً).
     * هذا السلوك مبني على افتراض، وينتظر تأكيد المشرفة.
     */
    public function test_register_again_after_cancellation_reopens_the_same_registration(): void
    {
        $opportunity = $this->makeOpportunity();
        $cancelled = $this->makeRegistration($opportunity, RegistrationStatus::Cancelled);
        $user = $cancelled->user;

        $result = $this->service->register($user, $opportunity);

        $this->assertSame($cancelled->id, $result->id);
        $this->assertSame(RegistrationStatus::Pending, $result->status);
        $this->assertSame(1, VolunteerRegistration::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('user_id', $user->id)
            ->count());
    }

    public function test_rejected_volunteer_posting_again_sees_a_clear_message_not_a_server_error(): void
    {
        $admin = $this->makeAdmin();
        $opportunity = $this->makeOpportunity();
        $registration = $this->makeRegistration($opportunity, RegistrationStatus::Pending);
        $volunteer = $registration->user;
        Profile::query()->create(['user_id' => $volunteer->id]);

        $this->service->reject($registration, $admin, 'غير مناسب');

        $this->actingAsOtpVerified($volunteer)
            ->post(route('public.volunteering.register', $opportunity))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(RegistrationStatus::Rejected, $registration->fresh()->status);
    }

    public function test_register_is_blocked_when_approved_registrations_fill_capacity(): void
    {
        $opportunity = $this->makeOpportunity(['capacity' => 1]);
        $this->makeRegistration($opportunity, RegistrationStatus::Approved);

        $this->expectException(OpportunityCapacityExceededException::class);

        $this->service->register($this->makeBeneficiary(), $opportunity);
    }

    public function test_pending_registrations_do_not_consume_capacity(): void
    {
        $opportunity = $this->makeOpportunity(['capacity' => 1]);
        $this->makeRegistration($opportunity, RegistrationStatus::Pending);

        $registration = $this->service->register($this->makeBeneficiary(), $opportunity);

        $this->assertSame(RegistrationStatus::Pending, $registration->status);
    }

    // ─── القبول (approve) ────────────────────────────────────────────────────

    public function test_approve_marks_registration_approved_and_records_who_approved(): void
    {
        $admin = $this->makeAdmin();
        $registration = $this->makeRegistration($this->makeOpportunity(), RegistrationStatus::Pending);

        $result = $this->service->approve($registration, $admin);

        $this->assertSame(RegistrationStatus::Approved, $result->status);
        $this->assertSame($admin->id, $result->approved_by);
        $this->assertNotNull($result->approved_at);
    }

    public function test_approve_throws_when_capacity_is_already_full(): void
    {
        $admin = $this->makeAdmin();
        $opportunity = $this->makeOpportunity(['capacity' => 1]);
        $this->makeRegistration($opportunity, RegistrationStatus::Approved);
        $pending = $this->makeRegistration($opportunity, RegistrationStatus::Pending);

        try {
            $this->service->approve($pending, $admin);
            $this->fail('كان يجب أن يرمي استثناء تجاوز السعة.');
        } catch (OpportunityCapacityExceededException) {
            $this->assertSame(RegistrationStatus::Pending, $pending->fresh()->status);
        }
    }

    public function test_approve_ignores_registration_that_is_not_pending(): void
    {
        $admin = $this->makeAdmin();
        $registration = $this->makeRegistration($this->makeOpportunity(), RegistrationStatus::Rejected);

        $result = $this->service->approve($registration, $admin);

        $this->assertSame(RegistrationStatus::Rejected, $result->status);
        $this->assertNull($result->fresh()->approved_by);
    }

    // ─── الرفض (reject) ──────────────────────────────────────────────────────

    public function test_reject_marks_registration_rejected_and_stores_reason(): void
    {
        $admin = $this->makeAdmin();
        $registration = $this->makeRegistration($this->makeOpportunity(), RegistrationStatus::Pending);

        $result = $this->service->reject($registration, $admin, 'لا تنطبق الشروط');

        $this->assertSame(RegistrationStatus::Rejected, $result->status);
        $this->assertSame('لا تنطبق الشروط', $result->rejected_reason);
    }

    public function test_reject_works_without_a_reason(): void
    {
        $admin = $this->makeAdmin();
        $registration = $this->makeRegistration($this->makeOpportunity(), RegistrationStatus::Pending);

        $result = $this->service->reject($registration, $admin);

        $this->assertSame(RegistrationStatus::Rejected, $result->status);
        $this->assertNull($result->rejected_reason);
    }

    // ─── الإكمال والإلغاء ────────────────────────────────────────────────────

    public function test_mark_completed_only_applies_to_approved_registrations(): void
    {
        $admin = $this->makeAdmin();
        $opportunity = $this->makeOpportunity();
        $approved = $this->makeRegistration($opportunity, RegistrationStatus::Approved);
        $pending = $this->makeRegistration($opportunity, RegistrationStatus::Pending);

        $this->assertSame(RegistrationStatus::Completed, $this->service->markCompleted($approved, $admin)->status);
        $this->assertSame(RegistrationStatus::Pending, $this->service->markCompleted($pending, $admin)->status);
    }

    public function test_cancel_marks_registration_cancelled(): void
    {
        $registration = $this->makeRegistration($this->makeOpportunity(), RegistrationStatus::Approved);

        $result = $this->service->cancel($registration);

        $this->assertSame(RegistrationStatus::Cancelled, $result->status);
    }

    /**
     * يوثّق السلوك الحالي (وليس بالضرورة المطلوب): عند تحوّل التسجيل إلى "مكتمل"
     * يتحرر مقعده لأن حساب السعة يعدّ حالة "مقبول" فقط.
     * القرار بتغييره أو إبقائه ينتظر رأي المشرفة.
     */
    public function test_completed_registration_currently_frees_its_capacity_slot(): void
    {
        $admin = $this->makeAdmin();
        $opportunity = $this->makeOpportunity(['capacity' => 1]);
        $registration = $this->makeRegistration($opportunity, RegistrationStatus::Approved);

        $this->assertFalse($opportunity->fresh()->hasCapacity());

        $this->service->markCompleted($registration, $admin);

        $this->assertTrue($opportunity->fresh()->hasCapacity());
    }

    // ─── لوحة الإدارة: تبويب التسجيلات داخل صفحة الفرصة ─────────────────────

    public function test_approve_action_in_registrations_tab_approves_the_registration(): void
    {
        [$admin, $opportunity, $registration] = $this->adminOpportunityAndPendingRegistration();

        Livewire::actingAs($admin)
            ->test(RegistrationsRelationManager::class, [
                'ownerRecord' => $opportunity,
                'pageClass' => ViewVolunteerOpportunity::class,
            ])
            ->callAction(TestAction::make('approve')->table($registration));

        $this->assertSame(RegistrationStatus::Approved, $registration->fresh()->status);
    }

    /**
     * هذا الاختبار يكشف الخطأ: زر "رفض" في تبويب التسجيلات يمرّر السبب
     * مكان المستخدم الذي رفض، فيرمي TypeError ولا يتم الرفض.
     */
    public function test_reject_action_in_registrations_tab_rejects_the_registration(): void
    {
        [$admin, $opportunity, $registration] = $this->adminOpportunityAndPendingRegistration();

        Livewire::actingAs($admin)
            ->test(RegistrationsRelationManager::class, [
                'ownerRecord' => $opportunity,
                'pageClass' => ViewVolunteerOpportunity::class,
            ])
            ->callAction(TestAction::make('reject')->table($registration), [
                'rejected_reason' => 'غير مناسب',
            ]);

        $registration = $registration->fresh();
        $this->assertSame(RegistrationStatus::Rejected, $registration->status);
        $this->assertSame('غير مناسب', $registration->rejected_reason);
    }

    // ─── أدوات مساعدة ────────────────────────────────────────────────────────

    /**
     * @return array{0: User, 1: VolunteerOpportunity, 2: VolunteerRegistration}
     */
    private function adminOpportunityAndPendingRegistration(): array
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->withSession(['otp_verified' => true]);

        $admin = $this->makeAdmin();
        $opportunity = $this->makeOpportunity();
        $registration = $this->makeRegistration($opportunity, RegistrationStatus::Pending);

        return [$admin, $opportunity, $registration];
    }

    private function makeOpportunity(array $overrides = []): VolunteerOpportunity
    {
        return VolunteerOpportunity::query()->create(array_merge([
            'title' => 'فرصة تطوعية للاختبار',
            'slug' => 'volunteer-test-'.uniqid(),
            'status' => OpportunityStatus::Published,
            'published_at' => now(),
            'notify_on_publish' => false,
        ], $overrides));
    }

    private function makeRegistration(VolunteerOpportunity $opportunity, RegistrationStatus $status): VolunteerRegistration
    {
        return VolunteerRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $this->makeBeneficiary()->id,
            'status' => $status,
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

    private function makeAdmin(): User
    {
        $admin = User::factory()->create([
            'role_type' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        return $admin;
    }
}