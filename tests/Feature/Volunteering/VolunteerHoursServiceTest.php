<?php

namespace Tests\Feature\Volunteering;

use App\Enums\OpportunityStatus;
use App\Enums\RegistrationStatus;
use App\Enums\VolunteerHoursStatus;
use App\Filament\Resources\VolunteerOpportunityResource\Pages\EditVolunteerOpportunity;
use App\Filament\Resources\VolunteerOpportunityResource\Pages\ViewVolunteerOpportunity;
use App\Filament\Resources\VolunteerOpportunityResource\RelationManagers\VolunteerHoursRelationManager;
use App\Models\Certificate;
use App\Models\User;
use App\Models\VolunteerHour;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
use App\Services\CertificateService;
use App\Services\VolunteerHoursService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

/**
 * اختبارات خدمة الساعات التطوعية (VolunteerHoursService)
 * واختبار تبويب "ساعات التطوع" داخل صفحة الفرصة في لوحة الإدارة.
 *
 * خدمة الشهادات تُستبدل بنسخة وهمية (Mock) حتى لا يُنشأ ملف PDF حقيقي.
 */
class VolunteerHoursServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        Notification::fake();
    }

    // ─── إضافة الساعات ───────────────────────────────────────────────────────

    public function test_add_hours_creates_a_pending_entry(): void
    {
        $service = $this->hoursService();
        $admin = $this->makeAdmin();
        $volunteer = $this->makeUser();
        $opportunity = $this->makeOpportunity();

        $hour = $service->addHours($volunteer, $opportunity, 3.5, $admin);

        $this->assertSame(VolunteerHoursStatus::Pending, $hour->status);
        $this->assertDatabaseHas('volunteer_hours', [
            'user_id' => $volunteer->id,
            'opportunity_id' => $opportunity->id,
        ]);
        $this->assertEquals(3.5, (float) $hour->hours);
    }

    // ─── اعتماد الساعات ──────────────────────────────────────────────────────

    public function test_approve_hours_marks_entry_approved_and_records_the_approver(): void
    {
        $service = $this->hoursService();
        $admin = $this->makeAdmin();
        $opportunity = $this->makeOpportunity(['hours_expected' => 10]);
        $registration = $this->makeApprovedRegistration($opportunity);
        $hour = $this->makeHour($registration, 2);

        $result = $service->approveHours($hour, $admin);

        $this->assertSame(VolunteerHoursStatus::Approved, $result->status);
        $this->assertSame($admin->id, $result->approved_by);
        $this->assertNotNull($result->approved_at);
    }

    public function test_approving_an_already_approved_entry_changes_nothing(): void
    {
        $service = $this->hoursService();
        $admin = $this->makeAdmin();
        $other = $this->makeAdmin();
        $opportunity = $this->makeOpportunity(['hours_expected' => 10]);
        $registration = $this->makeApprovedRegistration($opportunity);
        $hour = $this->makeHour($registration, 2);

        $service->approveHours($hour, $admin);
        $result = $service->approveHours($hour->fresh(), $other);

        $this->assertSame($admin->id, $result->approved_by);
    }

    public function test_registration_stays_approved_while_hours_are_below_the_requirement(): void
    {
        $service = $this->hoursService(certificateIssued: 0);
        $admin = $this->makeAdmin();
        $opportunity = $this->makeOpportunity(['hours_expected' => 10]);
        $registration = $this->makeApprovedRegistration($opportunity);

        $service->approveHours($this->makeHour($registration, 4), $admin);

        $this->assertSame(RegistrationStatus::Approved, $registration->fresh()->status);
    }

    public function test_registration_is_completed_and_certificate_issued_when_requirement_is_met(): void
    {
        $service = $this->hoursService(certificateIssued: 1);
        $admin = $this->makeAdmin();
        $opportunity = $this->makeOpportunity(['hours_expected' => 10]);
        $registration = $this->makeApprovedRegistration($opportunity);

        $service->approveHours($this->makeHour($registration, 6), $admin);
        $this->assertSame(RegistrationStatus::Approved, $registration->fresh()->status);

        $service->approveHours($this->makeHour($registration, 4), $admin);
        $this->assertSame(RegistrationStatus::Completed, $registration->fresh()->status);
    }

    public function test_pending_and_rejected_hours_do_not_count_toward_the_requirement(): void
    {
        $service = $this->hoursService(certificateIssued: 0);
        $admin = $this->makeAdmin();
        $opportunity = $this->makeOpportunity(['hours_expected' => 10]);
        $registration = $this->makeApprovedRegistration($opportunity);

        $this->makeHour($registration, 8, VolunteerHoursStatus::Pending);
        $this->makeHour($registration, 8, VolunteerHoursStatus::Rejected);
        $service->approveHours($this->makeHour($registration, 2), $admin);

        $this->assertSame(RegistrationStatus::Approved, $registration->fresh()->status);
    }

    public function test_hours_from_another_opportunity_do_not_count(): void
    {
        $service = $this->hoursService(certificateIssued: 0);
        $admin = $this->makeAdmin();
        $opportunityA = $this->makeOpportunity(['hours_expected' => 10]);
        $opportunityB = $this->makeOpportunity(['hours_expected' => 10]);
        $registrationA = $this->makeApprovedRegistration($opportunityA);

        // ساعات معتمدة لنفس المتطوع لكن في فرصة مختلفة
        VolunteerHour::query()->create([
            'user_id' => $registrationA->user_id,
            'opportunity_id' => $opportunityB->id,
            'hours' => 9,
            'status' => VolunteerHoursStatus::Approved,
        ]);

        $service->approveHours($this->makeHour($registrationA, 2), $admin);

        $this->assertSame(RegistrationStatus::Approved, $registrationA->fresh()->status);
    }

    public function test_opportunity_without_required_hours_never_completes_automatically(): void
    {
        $service = $this->hoursService(certificateIssued: 0);
        $admin = $this->makeAdmin();
        $opportunity = $this->makeOpportunity(['hours_expected' => null]);
        $registration = $this->makeApprovedRegistration($opportunity);

        $service->approveHours($this->makeHour($registration, 50), $admin);

        $this->assertSame(RegistrationStatus::Approved, $registration->fresh()->status);
    }

    // ─── رفض الساعات ─────────────────────────────────────────────────────────

    public function test_reject_hours_marks_entry_rejected(): void
    {
        $service = $this->hoursService(certificateIssued: 0);
        $admin = $this->makeAdmin();
        $registration = $this->makeApprovedRegistration($this->makeOpportunity(['hours_expected' => 10]));
        $hour = $this->makeHour($registration, 3);

        $result = $service->rejectHours($hour, $admin);

        $this->assertSame(VolunteerHoursStatus::Rejected, $result->status);
    }

    /**
     * يوثّق السلوك الحالي (وليس بالضرورة المطلوب): رفض ساعات سبق اعتمادها
     * بعد اكتمال التسجيل لا يعيد التسجيل إلى "مقبول" ولا يسحب الشهادة.
     */
    public function test_rejecting_previously_approved_hours_does_not_undo_completion(): void
    {
        $service = $this->hoursService(certificateIssued: 1);
        $admin = $this->makeAdmin();
        $opportunity = $this->makeOpportunity(['hours_expected' => 5]);
        $registration = $this->makeApprovedRegistration($opportunity);
        $hour = $this->makeHour($registration, 5);

        $service->approveHours($hour, $admin);
        $this->assertSame(RegistrationStatus::Completed, $registration->fresh()->status);

        $service->rejectHours($hour->fresh(), $admin);

        $this->assertSame(VolunteerHoursStatus::Rejected, $hour->fresh()->status);
        $this->assertSame(RegistrationStatus::Completed, $registration->fresh()->status);
    }

    /**
     * لو فشل إصدار الشهادة يُلغى الاعتماد كله (الساعات والإكمال)، فتنجح
     * إعادة المحاولة لاحقاً بدل أن يبقى تسجيل «مكتمل» بلا شهادة.
     */
    public function test_certificate_failure_rolls_back_the_approval_and_retry_succeeds(): void
    {
        $admin = $this->makeAdmin();
        $opportunity = $this->makeOpportunity(['hours_expected' => 5]);
        $registration = $this->makeApprovedRegistration($opportunity);
        $hour = $this->makeHour($registration, 5);

        $calls = 0;
        $this->mock(CertificateService::class, function ($mock) use (&$calls): void {
            $mock->shouldReceive('issue')->twice()->andReturnUsing(function () use (&$calls) {
                $calls++;
                if ($calls === 1) {
                    throw new RuntimeException('PDF failed');
                }

                return new Certificate;
            });
        });
        $service = app(VolunteerHoursService::class);

        try {
            $service->approveHours($hour, $admin);
            $this->fail('كان يجب أن يرمي استثناء فشل الشهادة.');
        } catch (RuntimeException) {
            // متوقع
        }

        $this->assertSame(VolunteerHoursStatus::Pending, $hour->fresh()->status);
        $this->assertSame(RegistrationStatus::Approved, $registration->fresh()->status);

        $service->approveHours($hour->fresh(), $admin);

        $this->assertSame(VolunteerHoursStatus::Approved, $hour->fresh()->status);
        $this->assertSame(RegistrationStatus::Completed, $registration->fresh()->status);
    }

    // ─── لوحة الإدارة: تبويب "ساعات التطوع" داخل صفحة الفرصة ────────────────

    /**
     * يكشف الخطأ: الزر يستدعي approve() وهذه الدالة غير موجودة في الخدمة
     * (الاسم الصحيح approveHours).
     */
    public function test_approve_action_in_hours_tab_approves_the_entry(): void
    {
        [$admin, $opportunity, $hour] = $this->adminOpportunityAndPendingHour();

        Livewire::actingAs($admin)
            ->test(VolunteerHoursRelationManager::class, [
                'ownerRecord' => $opportunity,
                'pageClass' => ViewVolunteerOpportunity::class,
            ])
            ->callAction(TestAction::make('approve')->table($hour));

        $this->assertSame(VolunteerHoursStatus::Approved, $hour->fresh()->status);
    }

    /**
     * يكشف الخطأ: الزر يستدعي reject() وهذه الدالة غير موجودة
     * (الاسم الصحيح rejectHours، وتتوقع المستخدم الذي رفض كوسيط ثانٍ).
     */
    public function test_reject_action_in_hours_tab_rejects_the_entry(): void
    {
        [$admin, $opportunity, $hour] = $this->adminOpportunityAndPendingHour();

        Livewire::actingAs($admin)
            ->test(VolunteerHoursRelationManager::class, [
                'ownerRecord' => $opportunity,
                'pageClass' => ViewVolunteerOpportunity::class,
            ])
            ->callAction(TestAction::make('reject')->table($hour), [
                'notes' => 'ساعات غير مؤكدة',
            ]);

        $this->assertSame(VolunteerHoursStatus::Rejected, $hour->fresh()->status);
    }

    /**
     * الساعات المعتمدة أو المرفوضة مقفلة: لا تعديل ولا حذف إلا للساعات
     * «قيد الانتظار»، حتى لا تتغير الأرقام بعد الاعتماد أو إصدار الشهادة.
     */
    public function test_only_pending_hours_can_be_edited_or_deleted_in_the_hours_tab(): void
    {
        [$admin, $opportunity, $pending] = $this->adminOpportunityAndPendingHour();
        $registration = VolunteerRegistration::query()->where('opportunity_id', $opportunity->id)->first();
        $approved = $this->makeHour($registration, 3, VolunteerHoursStatus::Approved);

        Livewire::actingAs($admin)
            ->test(VolunteerHoursRelationManager::class, [
                'ownerRecord' => $opportunity,
                'pageClass' => EditVolunteerOpportunity::class,
            ])
            ->assertActionVisible(TestAction::make('edit')->table($pending))
            ->assertActionHidden(TestAction::make('edit')->table($approved))
            ->assertActionVisible(TestAction::make('delete')->table($pending))
            ->assertActionHidden(TestAction::make('delete')->table($approved));
    }

    /**
     * يكشف الخطأ: نموذج الإضافة في التبويب فيه "الساعات" و"الملاحظات" فقط،
     * بدون اختيار المتطوع، بينما العمود user_id إلزامي في قاعدة البيانات.
     */
    public function test_create_action_in_hours_tab_creates_an_hours_entry(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->withSession(['otp_verified' => true]);

        $admin = $this->makeAdmin();
        $opportunity = $this->makeOpportunity(['hours_expected' => 10]);
        $registration = $this->makeApprovedRegistration($opportunity);

        // صفحة التعديل (وليس العرض): التبويبات في صفحة العرض للقراءة فقط
        // فيختفي زر الإضافة فيها.
        Livewire::actingAs($admin)
            ->test(VolunteerHoursRelationManager::class, [
                'ownerRecord' => $opportunity,
                'pageClass' => EditVolunteerOpportunity::class,
            ])
            ->callAction(TestAction::make('create')->table(), [
                'user_id' => $registration->user_id,
                'hours' => 2,
                'notes' => 'تجربة',
            ]);

        $hour = VolunteerHour::query()->where('opportunity_id', $opportunity->id)->first();
        $this->assertNotNull($hour);
        $this->assertSame($registration->user_id, $hour->user_id);
        $this->assertSame(VolunteerHoursStatus::Pending, $hour->status);
    }

    // ─── أدوات مساعدة ────────────────────────────────────────────────────────

    /**
     * يبني خدمة الساعات مع نسخة وهمية من خدمة الشهادات.
     *
     * @param  int  $certificateIssued  عدد المرات المتوقع فيها إصدار الشهادة
     */
    private function hoursService(int $certificateIssued = 0): VolunteerHoursService
    {
        $this->mock(CertificateService::class, function ($mock) use ($certificateIssued): void {
            if ($certificateIssued === 0) {
                $mock->shouldNotReceive('issue');

                return;
            }

            $mock->shouldReceive('issue')->times($certificateIssued)->andReturn(new Certificate);
        });

        return app(VolunteerHoursService::class);
    }

    /**
     * @return array{0: User, 1: VolunteerOpportunity, 2: VolunteerHour}
     */
    private function adminOpportunityAndPendingHour(): array
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->withSession(['otp_verified' => true]);

        $admin = $this->makeAdmin();
        $opportunity = $this->makeOpportunity(['hours_expected' => 10]);
        $registration = $this->makeApprovedRegistration($opportunity);
        $hour = $this->makeHour($registration, 2);

        return [$admin, $opportunity, $hour];
    }

    private function makeHour(
        VolunteerRegistration $registration,
        float $hours,
        VolunteerHoursStatus $status = VolunteerHoursStatus::Pending,
    ): VolunteerHour {
        return VolunteerHour::query()->create([
            'user_id' => $registration->user_id,
            'opportunity_id' => $registration->opportunity_id,
            'hours' => $hours,
            'status' => $status,
        ]);
    }

    private function makeApprovedRegistration(VolunteerOpportunity $opportunity): VolunteerRegistration
    {
        return VolunteerRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $this->makeUser()->id,
            'status' => RegistrationStatus::Approved,
        ]);
    }

    private function makeOpportunity(array $overrides = []): VolunteerOpportunity
    {
        return VolunteerOpportunity::query()->create(array_merge([
            'title' => 'فرصة تطوعية للاختبار',
            'slug' => 'volunteer-hours-test-'.uniqid(),
            'status' => OpportunityStatus::Published,
            'published_at' => now(),
            'notify_on_publish' => false,
        ], $overrides));
    }

    private function makeUser(): User
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
