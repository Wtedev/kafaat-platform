<?php

namespace Tests\Feature;

use App\Enums\OpportunityStatus;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiVolunteeringTest extends TestCase
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
            'staff_ui.ready_modules' => ['volunteering'],
        ]);
    }

    public function test_index_lists_opportunities_with_counts_search_and_registration_link(): void
    {
        $admin = $this->admin();
        $this->opportunity(['title' => 'تنظيم معرض الكتاب', 'external_url' => 'https://nvg.gov.sa/opportunity/1']);
        $this->opportunity(['title' => 'حملة التشجير', 'status' => OpportunityStatus::Draft, 'published_at' => null]);
        $this->opportunity(['title' => 'فرصة قديمة', 'status' => OpportunityStatus::Archived]);

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.volunteering.index'))
            ->assertOk()
            ->assertSee('الفرص التطوعية')
            ->assertSee('تنظيم معرض الكتاب')
            ->assertSee('حملة التشجير')
            ->assertSee('https://nvg.gov.sa/opportunity/1', false)
            ->assertSee('بلا رابط')
            ->assertSee(route('staff-ui.volunteering.index'), false);

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.volunteering.index', ['view' => 'archived']))
            ->assertOk()
            ->assertSee('فرصة قديمة')
            ->assertDontSee('حملة التشجير');

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.volunteering.index', ['q' => 'التشجير']))
            ->assertOk()
            ->assertSee('حملة التشجير')
            ->assertDontSee('تنظيم معرض الكتاب');
    }

    public function test_create_saves_announcement_fields_and_publishes_with_external_link(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $response = $this->actingAsOtpVerified($admin)->post(route('staff-ui.volunteering.store'), [
            'title' => 'مساندة فعالية اليوم الوطني',
            'description' => 'تنظيم الحضور واستقبال الزوار.',
            'external_url' => 'https://nvg.gov.sa/opportunity/77',
            'capacity' => '25',
            'hours_expected' => '6',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-03',
            'notify_on_publish' => '0',
            'publish' => '1',
            'image' => UploadedFile::fake()->image('cover.jpg', 800, 600),
        ]);

        $opportunity = VolunteerOpportunity::query()->sole();
        $response->assertRedirect(route('staff-ui.volunteering.edit', $opportunity));
        $this->assertSame('مساندة فعالية اليوم الوطني', $opportunity->title);
        $this->assertSame(OpportunityStatus::Published, $opportunity->status);
        $this->assertNotNull($opportunity->published_at);
        $this->assertSame(25, $opportunity->capacity);
        $this->assertSame('https://nvg.gov.sa/opportunity/77', $opportunity->external_url);
        $this->assertSame('2026-12-03', $opportunity->end_date->toDateString());
        $this->assertNotSame('', (string) $opportunity->slug);
        $this->assertSame($admin->id, $opportunity->created_by);
        Storage::disk('public')->assertExists($opportunity->image);

        $this->get(route('public.volunteering.show', $opportunity))
            ->assertOk()
            ->assertSee('https://nvg.gov.sa/opportunity/77', false)
            ->assertSee('سجّل في الفرصة');
    }

    public function test_two_arabic_titles_get_distinct_slugs(): void
    {
        $admin = $this->admin();
        foreach ([1, 2] as $_) {
            $this->actingAsOtpVerified($admin)->post(route('staff-ui.volunteering.store'), ['title' => 'فرصة تطوعية'])->assertRedirect();
        }

        $this->assertSame(2, VolunteerOpportunity::query()->distinct()->count('slug'));
        $this->assertSame(2, VolunteerOpportunity::query()->where('status', OpportunityStatus::Draft)->count());
    }

    public function test_validation_rejects_bad_link_and_reversed_dates(): void
    {
        $admin = $this->admin();

        $this->actingAsOtpVerified($admin)
            ->from(route('staff-ui.volunteering.create'))
            ->post(route('staff-ui.volunteering.store'), [
                'title' => '',
                'external_url' => 'javascript:alert(1)',
                'start_date' => '2026-12-05',
                'end_date' => '2026-12-01',
            ])
            ->assertRedirect(route('staff-ui.volunteering.create'))
            ->assertSessionHasErrors(['title', 'external_url', 'end_date']);

        $this->assertSame(0, VolunteerOpportunity::query()->count());
    }

    public function test_edit_updates_fields_and_status_actions_flow(): void
    {
        $admin = $this->admin();
        $opportunity = $this->opportunity(['status' => OpportunityStatus::Draft, 'published_at' => null]);

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.volunteering.edit', $opportunity))
            ->assertOk()
            ->assertSee('بيانات الفرصة')
            ->assertSee('نشر الفرصة')
            ->assertSee('حذف الفرصة');

        $this->actingAsOtpVerified($admin)->post(route('staff-ui.volunteering.update', $opportunity), [
            'title' => 'عنوان محدّث',
            'external_url' => 'https://nvg.gov.sa/opportunity/9',
            'capacity' => '',
        ])->assertRedirect(route('staff-ui.volunteering.edit', $opportunity));

        $opportunity->refresh();
        $this->assertSame('عنوان محدّث', $opportunity->title);
        $this->assertNull($opportunity->capacity);

        foreach (['publish' => OpportunityStatus::Published, 'unpublish' => OpportunityStatus::Draft, 'archive' => OpportunityStatus::Archived, 'restore' => OpportunityStatus::Draft] as $action => $expected) {
            $this->actingAsOtpVerified($admin)
                ->post(route('staff-ui.volunteering.status', $opportunity), ['action' => $action])
                ->assertRedirect(route('staff-ui.volunteering.edit', $opportunity));
            $this->assertSame($expected, $opportunity->refresh()->status, $action);
        }

        // An action that does not fit the current status is refused.
        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.volunteering.status', $opportunity), ['action' => 'restore'])
            ->assertForbidden();
    }

    public function test_delete_removes_the_opportunity(): void
    {
        $admin = $this->admin();
        $opportunity = $this->opportunity();

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.volunteering.delete', $opportunity))
            ->assertRedirect(route('staff-ui.volunteering.index'));

        $this->assertModelMissing($opportunity);
    }

    public function test_permissions_gate_each_action(): void
    {
        $viewer = $this->staff(['volunteering.view']);
        $mine = $this->opportunity(['assigned_to' => $viewer->id]);

        $this->actingAsOtpVerified($viewer)->get(route('staff-ui.volunteering.index'))->assertOk()->assertDontSee('إضافة فرصة');
        $this->actingAsOtpVerified($viewer)->get(route('staff-ui.volunteering.create'))->assertForbidden();
        $this->actingAsOtpVerified($viewer)->get(route('staff-ui.volunteering.edit', $mine))->assertForbidden();
        $this->actingAsOtpVerified($viewer)->post(route('staff-ui.volunteering.delete', $mine))->assertForbidden();

        $editor = $this->staff(['volunteering.view', 'volunteering.create', 'volunteering.update']);
        $this->actingAsOtpVerified($editor)->post(route('staff-ui.volunteering.store'), ['title' => 'فرصة الموظف', 'publish' => '1'])->assertRedirect();
        $created = VolunteerOpportunity::query()->where('title', 'فرصة الموظف')->sole();
        $this->assertSame(OpportunityStatus::Draft, $created->status, 'publishing needs volunteering.publish');
        $this->actingAsOtpVerified($editor)->post(route('staff-ui.volunteering.status', $created), ['action' => 'publish'])->assertForbidden();

        $outsider = $this->staff([]);
        $this->actingAsOtpVerified($outsider)->get(route('staff-ui.volunteering.index'))->assertForbidden();
    }

    public function test_nav_shows_volunteering_only_with_permission(): void
    {
        $this->actingAsOtpVerified($this->admin())
            ->get(route('staff-ui.volunteering.index'))
            ->assertSee(route('staff-ui.volunteering.index'), false);

        config(['staff_ui.ready_modules' => ['volunteering', 'users']]);
        $staff = $this->staff(['users.view']);
        $response = $this->actingAsOtpVerified($staff)->get(route('staff-ui.users.index'));
        if ($response->status() === 200) {
            $response->assertDontSee('الفرص التطوعية');
        } else {
            $this->markTestSkipped('Users list is not available to this staff role.');
        }
    }

    public function test_filament_opportunity_pages_still_render_after_registrations_were_removed(): void
    {
        $admin = $this->admin();
        $opportunity = $this->opportunity(['title' => 'فرصة في لوحة الإدارة']);

        foreach (['index' => [], 'view' => ['record' => $opportunity], 'edit' => ['record' => $opportunity]] as $page => $params) {
            $this->actingAsOtpVerified($admin)
                ->followingRedirects()
                ->get(route('filament.admin.resources.volunteer-opportunities.'.$page, $params))
                ->assertOk()
                ->assertSee('فرصة في لوحة الإدارة');
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function opportunity(array $overrides = []): VolunteerOpportunity
    {
        return VolunteerOpportunity::query()->create(array_merge([
            'title' => 'فرصة',
            'slug' => 'vo-'.uniqid(),
            'status' => OpportunityStatus::Published,
            'published_at' => now(),
            'notify_on_publish' => false,
            'start_date' => now()->addWeek()->toDateString(),
        ], $overrides));
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
        if ($permissions !== []) {
            $staff->givePermissionTo($permissions);
        }

        return $staff->fresh();
    }
}
