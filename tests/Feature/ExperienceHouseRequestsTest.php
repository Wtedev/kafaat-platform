<?php

namespace Tests\Feature;

use App\Filament\Resources\ExperienceHouseRequestResource\Pages\EditExperienceHouseRequest;
use App\Filament\Resources\ExperienceHouseRequestResource\Pages\ListExperienceHouseRequests;
use App\Models\ExperienceHouseRequest;
use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class ExperienceHouseRequestsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'organization_name' => 'جمعية الأمل الأهلية',
            'license_number' => '١٢٣٤',
            'region' => 'القصيم',
            'city' => 'بريدة',
            'founded_year' => '2019',
            'staff_size' => '6-15',
            'contact_name' => 'سارة محمد',
            'contact_role' => 'المديرة التنفيذية',
            'contact_phone' => '+966 55 123 4567',
            'contact_email' => 'sara@example.org',
            'departments' => ['strategic_planning', 'governance'],
            'support_types' => ['consultation', 'workshop'],
            'challenge' => 'لا تملك الجمعية خطة استراتيجية معتمدة ونحتاج إلى بنائها.',
            'expected_outcome' => 'خطة استراتيجية معتمدة.',
            'priority' => 'month',
            'delivery_mode' => 'remote',
            'consent' => '1',
        ], $overrides);
    }

    public function test_form_page_is_reachable_by_direct_link_and_not_indexed(): void
    {
        $this->get('/experience-houses')
            ->assertOk()
            ->assertSee('طلب دعم بيوت الخبرة')
            ->assertSee('تقديم طلب')
            ->assertSee(route('public.experience-houses.create'), false)
            ->assertSee('noindex', false);

        $this->get('/experience-houses/request')
            ->assertOk()
            ->assertSee('نموذج طلب الدعم')
            ->assertSee('noindex', false);
    }

    public function test_association_can_submit_a_request(): void
    {
        $response = $this->post('/experience-houses', $this->validPayload());

        $response->assertRedirect(route('public.experience-houses.done'));

        $record = ExperienceHouseRequest::query()->sole();
        $this->assertSame('0551234567', $record->contact_phone);
        $this->assertSame('1234', $record->license_number);
        $this->assertSame(['strategic_planning', 'governance'], $record->departments);
        $this->assertSame('new', $record->status);
        $this->assertSame(sprintf('BK-%s-%04d', $record->created_at->format('Y'), $record->id), $record->reference_no);
        $this->assertNull($record->attachment_path);
        $this->assertSame(['created'], $record->events()->pluck('type')->all());

        $this->followRedirects($response)->assertSee($record->reference_no);
    }

    public function test_validation_requires_department_and_other_text(): void
    {
        $this->post('/experience-houses', $this->validPayload(['departments' => []]))
            ->assertSessionHasErrors('departments');

        $this->post('/experience-houses', $this->validPayload(['departments' => ['other'], 'other_department' => '']))
            ->assertSessionHasErrors('other_department');

        $this->post('/experience-houses', $this->validPayload(['consent' => null]))
            ->assertSessionHasErrors('consent');

        $this->assertSame(0, ExperienceHouseRequest::query()->count());
    }

    public function test_honeypot_silently_drops_bots(): void
    {
        $this->post('/experience-houses', $this->validPayload(['website' => 'spam']))
            ->assertRedirect(route('public.experience-houses.create'));

        $this->assertSame(0, ExperienceHouseRequest::query()->count());
    }

    public function test_done_page_without_submission_redirects_to_form(): void
    {
        $this->get('/experience-houses/done')->assertRedirect(route('public.experience-houses.cover'));
    }

    public function test_admin_can_list_and_update_status(): void
    {
        $this->post('/experience-houses', $this->validPayload())->assertRedirect();
        $record = ExperienceHouseRequest::query()->sole();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create(['role_type' => 'admin', 'is_active' => true, 'email_verified_at' => now()]);
        $admin->assignRole(RbacCatalog::ROLE_ADMIN);

        Livewire::actingAs($admin)
            ->test(ListExperienceHouseRequests::class)
            ->assertCanSeeTableRecords([$record]);

        Livewire::actingAs($admin)
            ->test(EditExperienceHouseRequest::class, ['record' => $record->getRouteKey()])
            ->fillForm(['status' => 'scheduled', 'staff_notes' => 'تم التواصل وتحديد موعد'])
            ->call('save')
            ->assertHasNoFormErrors();

        $record->refresh();
        $this->assertSame('scheduled', $record->status);
        $this->assertSame('تم التواصل وتحديد موعد', $record->staff_notes);
    }
}
