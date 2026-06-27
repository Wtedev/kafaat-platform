<?php

namespace Tests\Unit\Privacy;

use App\Enums\PrivacyPolicyVersionStatus;
use App\Models\PrivacyPolicyVersion;
use App\Services\Privacy\PrivacyPolicyContentHasher;
use App\Services\Privacy\PrivacyPolicyPublisher;
use App\Services\Privacy\PrivacyPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyPolicyPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_activates_draft_and_archives_previous_active(): void
    {
        $old = PrivacyPolicyVersion::factory()->active()->create(['version' => '1.0']);
        $draft = PrivacyPolicyVersion::factory()->create(['version' => '2.0']);

        app(PrivacyPolicyPublisher::class)->publish($draft);

        $old->refresh();
        $draft->refresh();

        $this->assertSame(PrivacyPolicyVersionStatus::Archived, $old->status);
        $this->assertSame(PrivacyPolicyVersionStatus::Active, $draft->status);
        $this->assertNotNull($draft->published_at);
        $this->assertSame(PrivacyPolicyContentHasher::hash($draft->content), $draft->content_hash);
        $this->assertSame($draft->id, PrivacyPolicyService::active()?->id);
    }

    public function test_only_one_active_version_after_publish(): void
    {
        PrivacyPolicyVersion::factory()->active()->create(['version' => '1.0']);
        $draft = PrivacyPolicyVersion::factory()->create(['version' => '1.1']);

        app(PrivacyPolicyPublisher::class)->publish($draft);

        $this->assertSame(1, PrivacyPolicyVersion::query()->active()->count());
    }

    public function test_cannot_publish_non_draft(): void
    {
        $active = PrivacyPolicyVersion::factory()->active()->create();

        $this->expectException(\InvalidArgumentException::class);
        app(PrivacyPolicyPublisher::class)->publish($active);
    }

    public function test_cache_is_cleared_after_publish(): void
    {
        PrivacyPolicyVersion::factory()->active()->create(['version' => '1.0']);
        $this->assertNotNull(PrivacyPolicyService::active());

        $draft = PrivacyPolicyVersion::factory()->create(['version' => '2.0']);
        app(PrivacyPolicyPublisher::class)->publish($draft);

        $this->assertSame('2.0', PrivacyPolicyService::active()?->version);
    }
}
