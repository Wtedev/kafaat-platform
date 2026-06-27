<?php

namespace Tests\Feature\PrivacyPhase03;

use App\Models\PrivacyPolicyVersion;
use App\Services\Privacy\PrivacyPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsActivePrivacyPolicy;
use Tests\TestCase;

class PublicPrivacyPageTest extends TestCase
{
    use RefreshDatabase;
    use SeedsActivePrivacyPolicy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedActivePrivacyPolicy();
    }

    public function test_public_privacy_page_shows_active_version_metadata(): void
    {
        $policy = PrivacyPolicyService::activeOrFail();

        $this->get(route('public.privacy'))
            ->assertOk()
            ->assertSee($policy->version)
            ->assertSee('مقدمة')
            ->assertDontSee('now()');
    }

    public function test_archived_version_is_viewable_by_version_route(): void
    {
        $archived = PrivacyPolicyVersion::factory()->archived()->create([
            'version' => '0.9',
            'published_at' => now()->subYear(),
        ]);

        $this->get(route('public.privacy.version', ['version' => $archived->version]))
            ->assertOk()
            ->assertSee('0.9');
    }

    public function test_draft_version_is_not_publicly_accessible(): void
    {
        PrivacyPolicyVersion::factory()->create([
            'version' => 'draft-only',
            'status' => \App\Enums\PrivacyPolicyVersionStatus::Draft,
        ]);

        $this->get(route('public.privacy.version', ['version' => 'draft-only']))
            ->assertNotFound();
    }

    public function test_unavailable_page_when_no_active_policy(): void
    {
        PrivacyPolicyVersion::query()->delete();
        PrivacyPolicyService::forgetCache();

        $this->get(route('public.privacy'))
            ->assertOk()
            ->assertSee('غير متاحة حالياً');
    }

    public function test_script_tags_are_sanitized_from_display(): void
    {
        $policy = PrivacyPolicyService::activeOrFail();
        $policy->forceFill([
            'content' => '<section><p>نص</p><script>alert(1)</script></section>',
            'content_hash' => hash('sha256', 'test'),
        ])->save();
        PrivacyPolicyService::forgetCache();

        $response = $this->get(route('public.privacy'));
        $response->assertOk();
        $response->assertDontSee('alert(1)', false);
    }
}
