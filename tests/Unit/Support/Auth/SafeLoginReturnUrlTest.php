<?php

namespace Tests\Unit\Support\Auth;

use App\Enums\ProgramStatus;
use App\Models\TrainingProgram;
use App\Support\Auth\SafeLoginReturnUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafeLoginReturnUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanitize_accepts_published_program_path_with_query(): void
    {
        $program = $this->makePublishedProgram('leadership-return');

        $safe = SafeLoginReturnUrl::sanitize('/programs/leadership-return?ref=cta&utm=1');

        $this->assertSame('/programs/leadership-return?ref=cta&utm=1', $safe);
        $this->assertSame($program->slug, 'leadership-return');
    }

    public function test_sanitize_accepts_same_origin_absolute_url(): void
    {
        $this->makePublishedProgram('absolute-return');

        config(['app.url' => 'https://kafaat.org.sa']);

        $safe = SafeLoginReturnUrl::sanitize('https://kafaat.org.sa/programs/absolute-return?x=1');

        $this->assertSame('/programs/absolute-return?x=1', $safe);
    }

    public function test_sanitize_rejects_external_and_admin_urls(): void
    {
        $this->makePublishedProgram('safe-prog');

        config(['app.url' => 'https://kafaat.org.sa']);

        $this->assertNull(SafeLoginReturnUrl::sanitize('https://evil.example/programs/safe-prog'));
        $this->assertNull(SafeLoginReturnUrl::sanitize('//evil.example/programs/safe-prog'));
        $this->assertNull(SafeLoginReturnUrl::sanitize('/admin'));
        $this->assertNull(SafeLoginReturnUrl::sanitize('/admin/login'));
        $this->assertNull(SafeLoginReturnUrl::sanitize('/portal'));
        $this->assertNull(SafeLoginReturnUrl::sanitize('/programs/safe-prog/register'));
        $this->assertNull(SafeLoginReturnUrl::sanitize('/programs/../admin'));
    }

    public function test_sanitize_rejects_unpublished_or_missing_program(): void
    {
        TrainingProgram::query()->create([
            'title' => 'مسودة',
            'slug' => 'draft-prog',
            'status' => ProgramStatus::Draft,
            'published_at' => null,
            'learning_path_id' => null,
        ]);

        $this->assertNull(SafeLoginReturnUrl::sanitize('/programs/draft-prog'));
        $this->assertNull(SafeLoginReturnUrl::sanitize('/programs/does-not-exist'));
    }

    private function makePublishedProgram(string $slug): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => 'برنامج '.$slug,
            'slug' => $slug,
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
        ]);
    }
}
