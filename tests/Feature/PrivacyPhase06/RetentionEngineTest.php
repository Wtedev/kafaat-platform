<?php

namespace Tests\Feature\PrivacyPhase06;

use App\Enums\PrivacyExportFileStatus;
use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionPolicyStatus;
use App\Enums\RetentionRunMode;
use App\Enums\RetentionRunStatus;
use App\Enums\RetentionTriggerEvent;
use App\Models\EmailVerificationCode;
use App\Models\PrivacyExportFile;
use App\Models\RetentionException;
use App\Models\RetentionPolicy;
use App\Models\RetentionRun;
use App\Models\User;
use App\Services\Privacy\Retention\RetentionPolicyEngine;
use Database\Seeders\RetentionPolicySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RetentionEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RetentionPolicySeeder::class);
        Storage::fake('private_documents');
    }

    public function test_draft_policy_is_not_executed(): void
    {
        $policy = RetentionPolicy::query()->where('resource_type', 'email_verification_codes')->firstOrFail();
        $this->assertSame(RetentionPolicyStatus::Draft, $policy->status);

        EmailVerificationCode::query()->create([
            'user_id' => User::factory()->create()->id,
            'code_hash' => hash('sha256', '123456'),
            'attempts' => 0,
            'expires_at' => now()->subDays(10),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(RetentionPolicyEngine::class)->execute(policy: $policy);
    }

    public function test_preview_does_not_delete_records(): void
    {
        $policy = $this->makeActiveOtpPolicy();

        EmailVerificationCode::query()->create([
            'user_id' => User::factory()->create()->id,
            'code_hash' => hash('sha256', '123456'),
            'attempts' => 0,
            'expires_at' => now()->subDays(10),
        ]);

        $run = app(RetentionPolicyEngine::class)->preview($policy);

        $this->assertSame(RetentionRunMode::Preview, $run->mode);
        $this->assertSame(1, $run->eligible_count);
        $this->assertDatabaseCount('email_verification_codes', 1);
        $this->assertDatabaseHas('retention_runs', ['uuid' => $run->uuid]);
    }

    public function test_execute_deletes_expired_otp_with_active_policy(): void
    {
        $policy = $this->makeActiveOtpPolicy();

        EmailVerificationCode::query()->create([
            'user_id' => User::factory()->create()->id,
            'code_hash' => hash('sha256', '123456'),
            'attempts' => 0,
            'expires_at' => now()->subDays(10),
        ]);

        $run = app(RetentionPolicyEngine::class)->execute(policy: $policy);

        $this->assertSame(1, $run->succeeded_count);
        $this->assertDatabaseCount('email_verification_codes', 0);
    }

    public function test_active_otp_is_not_deleted(): void
    {
        $policy = $this->makeActiveOtpPolicy();

        EmailVerificationCode::query()->create([
            'user_id' => User::factory()->create()->id,
            'code_hash' => hash('sha256', '123456'),
            'attempts' => 0,
            'expires_at' => now()->addHour(),
        ]);

        $run = app(RetentionPolicyEngine::class)->execute(policy: $policy);

        $this->assertSame(0, $run->eligible_count);
        $this->assertDatabaseCount('email_verification_codes', 1);
    }

    public function test_activation_requires_fresh_preview(): void
    {
        $officer = $this->makeOfficer(['retention_policies.activate']);
        $policy = RetentionPolicy::query()->create([
            'resource_type' => 'email_verification_codes',
            'name' => 'Draft OTP activation test '.Str::uuid(),
            'trigger_type' => RetentionTriggerEvent::ExpiredAt,
            'retention_period_days' => 7,
            'grace_period_days' => 0,
            'action' => RetentionPolicyAction::Delete,
            'status' => RetentionPolicyStatus::Draft,
            'reason' => 'Test OTP cleanup policy for preview gate.',
            'effective_at' => now(),
        ]);

        $this->assertFalse(app(RetentionPolicyEngine::class)->canActivate($policy));

        app(RetentionPolicyEngine::class)->preview($policy);

        $this->assertTrue(app(RetentionPolicyEngine::class)->canActivate($policy->fresh()));

        app(RetentionPolicyEngine::class)->activate($policy->fresh(), $officer);

        $this->assertSame(RetentionPolicyStatus::Active, $policy->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'retention_policy.activated']);
    }

    public function test_retention_exception_blocks_deletion(): void
    {
        $policy = $this->makeActiveOtpPolicy();
        $user = User::factory()->create();

        $otp = EmailVerificationCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => hash('sha256', '123456'),
            'attempts' => 0,
            'expires_at' => now()->subDays(10),
        ]);

        RetentionException::query()->create([
            'resource_type' => 'email_verification_codes',
            'resource_id' => $otp->id,
            'scope' => 'single_resource',
            'reason_code' => 'regulatory_requirement',
            'reason' => 'Hold for regulatory review during test.',
            'starts_at' => now()->subDay(),
            'review_at' => now()->addMonth(),
            'status' => 'active',
            'approved_by' => $this->makeOfficer(['retention_exceptions.manage'])->id,
        ]);

        $run = app(RetentionPolicyEngine::class)->execute(policy: $policy);

        $this->assertSame(1, $run->excluded_count);
        $this->assertDatabaseCount('email_verification_codes', 1);
    }

    public function test_expired_export_purge_uses_retention_engine(): void
    {
        $user = User::factory()->create();
        $export = PrivacyExportFile::query()->create([
            'uuid' => (string) Str::uuid(),
            'privacy_request_id' => null,
            'user_id' => $user->id,
            'disk' => 'private_documents',
            'path' => 'privacy/exports/test.zip',
            'format' => 'zip',
            'status' => PrivacyExportFileStatus::Ready,
            'expires_at' => now()->subDay(),
        ]);

        Storage::disk('private_documents')->put($export->path, 'zip-content');

        Artisan::call('privacy:purge-expired-exports');

        $this->assertSame(PrivacyExportFileStatus::Deleted, $export->fresh()->status);
        Storage::disk('private_documents')->assertMissing($export->path);
    }

    public function test_non_expired_export_is_not_purged(): void
    {
        $export = PrivacyExportFile::query()->create([
            'uuid' => (string) Str::uuid(),
            'privacy_request_id' => null,
            'user_id' => User::factory()->create()->id,
            'disk' => 'private_documents',
            'path' => 'privacy/exports/active.zip',
            'format' => 'zip',
            'status' => PrivacyExportFileStatus::Ready,
            'expires_at' => now()->addDay(),
        ]);

        Storage::disk('private_documents')->put($export->path, 'zip-content');

        Artisan::call('privacy:purge-expired-exports');

        $this->assertSame(PrivacyExportFileStatus::Ready, $export->fresh()->status);
    }

    public function test_policy_without_retention_period_does_not_execute_delete(): void
    {
        $policy = RetentionPolicy::query()->where('resource_type', 'certificates')->firstOrFail();

        $this->expectException(\InvalidArgumentException::class);
        app(RetentionPolicyEngine::class)->execute(policy: $policy);
    }

    public function test_retention_status_command_runs(): void
    {
        $this->artisan('privacy:retention-status')->assertSuccessful();
    }

    public function test_resume_skips_completed_items(): void
    {
        $policy = $this->makeActiveOtpPolicy();

        EmailVerificationCode::query()->create([
            'user_id' => User::factory()->create()->id,
            'code_hash' => hash('sha256', '111111'),
            'attempts' => 0,
            'expires_at' => now()->subDays(10),
        ]);

        $firstRun = app(RetentionPolicyEngine::class)->execute(policy: $policy, maxItems: 1);
        $this->assertSame(RetentionRunStatus::Completed, $firstRun->status);

        $second = app(RetentionPolicyEngine::class)->execute(policy: $policy, resumeRun: $firstRun);
        $this->assertSame(0, $second->failed_count);
    }

    public function test_expired_session_cleanup_with_active_policy(): void
    {
        $policy = RetentionPolicy::query()->create([
            'resource_type' => 'sessions',
            'name' => 'Session cleanup test',
            'trigger_type' => RetentionTriggerEvent::LastActivityAt,
            'retention_period_days' => 0,
            'grace_period_days' => 0,
            'action' => RetentionPolicyAction::Delete,
            'status' => RetentionPolicyStatus::Active,
            'reason' => 'Test session cleanup for retention engine.',
            'effective_at' => now()->subMinute(),
        ]);

        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => User::factory()->create()->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode('test'),
            'last_activity' => now()->subDays(5)->timestamp,
        ]);

        $run = app(RetentionPolicyEngine::class)->execute(policy: $policy);

        $this->assertSame(1, $run->succeeded_count);
        $this->assertDatabaseCount('sessions', 0);
    }

    private function makeActiveOtpPolicy(): RetentionPolicy
    {
        $policy = RetentionPolicy::query()->where('resource_type', 'email_verification_codes')->firstOrFail();
        $policy->forceFill([
            'status' => RetentionPolicyStatus::Active,
            'effective_at' => now()->subMinute(),
            'last_previewed_at' => now(),
            'last_preview_count' => 0,
        ])->save();

        return $policy->fresh();
    }

    private function makeOfficer(array $permissions): User
    {
        $user = User::factory()->create(['email' => Str::uuid().'@example.com']);
        $user->givePermissionTo($permissions);

        return $user;
    }
}
