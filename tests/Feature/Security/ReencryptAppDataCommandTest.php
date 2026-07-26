<?php

namespace Tests\Feature\Security;

use App\Enums\IdentityType;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacyRequestType;
use App\Models\PrivacyCorrectionPayload;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Services\Identity\IdentityNumberService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\TestCase;

class ReencryptAppDataCommandTest extends TestCase
{
    use GeneratesTestIdentityData;
    use RefreshDatabase;

    private string $defaultAppKey;

    private string $oldAppKey;

    private string $newAppKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultAppKey = (string) config('app.key');
        $this->oldAppKey = $this->makeAppKey();
        $this->newAppKey = $this->makeAppKey();
    }

    protected function tearDown(): void
    {
        $this->bindAppKeys($this->defaultAppKey, []);

        parent::tearDown();
    }

    public function test_dry_run_does_not_write(): void
    {
        $identity = $this->generateValidNationalId();
        $user = $this->createUserWithOldCiphertext($identity);
        $before = (string) $user->fresh()->identity_number_ciphertext;
        $lookupBefore = (string) $user->fresh()->identity_number_lookup_hash;

        $this->bindAppKeys($this->newAppKey, [$this->oldAppKey]);

        $this->artisan('security:reencrypt-app-data', ['--dry-run' => true])
            ->expectsOutputToContain('scanned:')
            ->expectsOutputToContain('Mode: dry-run')
            ->assertSuccessful();

        $fresh = $user->fresh();
        $this->assertSame($before, $fresh->identity_number_ciphertext);
        $this->assertSame($lookupBefore, $fresh->identity_number_lookup_hash);
    }

    public function test_reencrypts_old_ciphertext_via_previous_keys(): void
    {
        $identity = $this->generateValidNationalId();
        $user = $this->createUserWithOldCiphertext($identity);
        $oldCiphertext = (string) $user->identity_number_ciphertext;
        $lookupBefore = (string) $user->identity_number_lookup_hash;

        $this->bindAppKeys($this->newAppKey, [$this->oldAppKey]);

        $this->artisan('security:reencrypt-app-data', ['--chunk' => 10])
            ->assertSuccessful()
            ->expectsOutputToContain('failed: 0');

        $fresh = $user->fresh();
        $newCiphertext = (string) $fresh->identity_number_ciphertext;

        $this->assertNotSame($oldCiphertext, $newCiphertext);
        $this->assertSame($lookupBefore, $fresh->identity_number_lookup_hash);
        $this->assertSame($identity, Crypt::decryptString($newCiphertext));

        // New ciphertext must not decrypt with the old key alone.
        $oldOnly = $this->encrypterForKey($this->oldAppKey);
        try {
            $oldOnly->decryptString($newCiphertext);
            $this->fail('Expected DecryptException when using old key alone.');
        } catch (DecryptException) {
            $this->assertTrue(true);
        }

        // New ciphertext must decrypt with current key and no previous keys.
        $this->bindAppKeys($this->newAppKey, []);
        $this->assertSame($identity, Crypt::decryptString($newCiphertext));
    }

    public function test_already_current_ciphertext_is_safely_processable(): void
    {
        $this->bindAppKeys($this->newAppKey, []);

        $identity = $this->generateValidNationalId();
        $payload = IdentityNumberService::prepareStoragePayload($identity, IdentityType::NationalId);
        $user = User::factory()->create([
            'identity_type' => IdentityType::NationalId->value,
            'identity_number_ciphertext' => $payload['identity_number_ciphertext'],
            'identity_number_lookup_hash' => $payload['identity_number_lookup_hash'],
            'identity_number_last4' => $payload['identity_number_last4'],
            'identity_confirmed_at' => $payload['identity_confirmed_at'],
        ]);

        $lookupBefore = (string) $user->identity_number_lookup_hash;

        $this->artisan('security:reencrypt-app-data')->assertSuccessful();

        $fresh = $user->fresh();
        $this->assertSame($lookupBefore, $fresh->identity_number_lookup_hash);
        $this->assertSame($identity, Crypt::decryptString((string) $fresh->identity_number_ciphertext));
    }

    public function test_null_and_empty_ciphertext_are_skipped(): void
    {
        User::factory()->create([
            'identity_number_ciphertext' => null,
            'identity_number_lookup_hash' => null,
        ]);
        User::factory()->create([
            'identity_number_ciphertext' => '',
            'identity_number_lookup_hash' => null,
        ]);

        $this->artisan('security:reencrypt-app-data', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('skipped_null: 2')
            ->expectsOutputToContain('eligible: 0')
            ->expectsOutputToContain('failed: 0');
    }

    public function test_corrupt_ciphertext_fails_closed_with_nonzero_exit(): void
    {
        $identity = $this->generateValidNationalId();
        $good = $this->createUserWithOldCiphertext($identity);

        $corrupt = User::factory()->create([
            'identity_type' => IdentityType::NationalId->value,
            'identity_number_ciphertext' => 'not-valid-laravel-ciphertext',
            'identity_number_lookup_hash' => hash('sha256', 'corrupt-fixture'),
            'identity_number_last4' => '0000',
        ]);

        $goodBefore = (string) $good->identity_number_ciphertext;
        $corruptBefore = (string) $corrupt->identity_number_ciphertext;
        $lookupCorrupt = (string) $corrupt->identity_number_lookup_hash;

        $this->bindAppKeys($this->newAppKey, [$this->oldAppKey]);

        $this->artisan('security:reencrypt-app-data', ['--chunk' => 50])
            ->assertFailed()
            ->expectsOutputToContain('failed:');

        $goodFresh = $good->fresh();
        $corruptFresh = $corrupt->fresh();

        // Good row may be re-encrypted; corrupt row must remain unchanged.
        $this->assertSame($corruptBefore, $corruptFresh->identity_number_ciphertext);
        $this->assertSame($lookupCorrupt, $corruptFresh->identity_number_lookup_hash);
        $this->assertNotSame($goodBefore, $goodFresh->identity_number_ciphertext);
        $this->assertSame($identity, Crypt::decryptString((string) $goodFresh->identity_number_ciphertext));
    }

    public function test_command_output_and_exceptions_never_include_plaintext_or_keys(): void
    {
        $identity = $this->generateValidNationalId();
        $this->createUserWithOldCiphertext($identity);
        $this->bindAppKeys($this->newAppKey, [$this->oldAppKey]);

        $exit = Artisan::call('security:reencrypt-app-data');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringNotContainsString($identity, $output);
        $this->assertStringNotContainsString($this->oldAppKey, $output);
        $this->assertStringNotContainsString($this->newAppKey, $output);
        $this->assertStringNotContainsString('base64:', $output);
    }

    public function test_privacy_correction_payloads_are_supported(): void
    {
        $email = 'correction-'.uniqid('', true).'@example.com';
        $oldCiphertext = $this->encryptWithKey($this->oldAppKey, $email);

        $user = User::factory()->create();
        $request = PrivacyRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'request_type' => PrivacyRequestType::DataCorrection,
            'status' => PrivacyRequestStatus::Submitted,
            'request_details' => ['field_code' => 'email'],
            'correction_field_code' => 'email',
        ]);

        $payload = PrivacyCorrectionPayload::query()->create([
            'privacy_request_id' => $request->id,
            'field_code' => 'email',
            'encrypted_value' => $oldCiphertext,
            'expires_at' => now()->addDay(),
        ]);

        $this->bindAppKeys($this->newAppKey, [$this->oldAppKey]);

        $this->artisan('security:reencrypt-app-data')->assertSuccessful();

        $fresh = $payload->fresh();
        $this->assertNotSame($oldCiphertext, $fresh->encrypted_value);

        $this->bindAppKeys($this->newAppKey, []);
        $this->assertSame($email, Crypt::decryptString((string) $fresh->encrypted_value));
    }

    public function test_batch_failure_does_not_corrupt_completed_batches_and_resume_is_safe(): void
    {
        $identities = [
            $this->generateValidNationalId(),
            $this->generateValidNationalId(),
            $this->generateValidNationalId(),
        ];

        $users = [];
        foreach ($identities as $identity) {
            $users[] = $this->createUserWithOldCiphertext($identity);
        }

        // Place corrupt ciphertext as id-ordered second row by creating after first, before third...
        // Chunk size 1: first batch succeeds; second (corrupt) fails; third succeeds.
        $corrupt = User::factory()->create([
            'identity_type' => IdentityType::NationalId->value,
            'identity_number_ciphertext' => 'totally-invalid-ciphertext',
            'identity_number_lookup_hash' => hash('sha256', 'batch-corrupt'),
            'identity_number_last4' => '1111',
        ]);

        // Ensure ordering: update corrupt to sit between user[0] and user[1] is hard with auto ids.
        // Instead use chunk=1 and insert corrupt between by id: delete and re-seed carefully.
        // Simpler: run with chunk=2 where batch1 = [good, good], batch2 = [good, corrupt] depending on ids.
        // Create corrupt last so IDs are: u0, u1, u2, corrupt.
        // chunk=2 → batch1: u0,u1 success; batch2: u2 success + corrupt fail → exit fail.
        // Completed batch1 stays; resume re-runs all safely.

        $this->bindAppKeys($this->newAppKey, [$this->oldAppKey]);

        $this->artisan('security:reencrypt-app-data', ['--chunk' => 2])
            ->assertFailed();

        $lookups = [];
        foreach ($users as $index => $user) {
            $fresh = $user->fresh();
            $lookups[$index] = (string) $fresh->identity_number_lookup_hash;
            $this->assertSame($identities[$index], Crypt::decryptString((string) $fresh->identity_number_ciphertext));
        }

        $this->assertSame(
            (string) $corrupt->identity_number_ciphertext,
            (string) $corrupt->fresh()->identity_number_ciphertext
        );

        // Resume: good rows remain decryptable; second run must not corrupt them.
        $this->artisan('security:reencrypt-app-data', ['--chunk' => 2])
            ->assertFailed();

        foreach ($users as $index => $user) {
            $fresh = $user->fresh();
            $this->assertSame($lookups[$index], (string) $fresh->identity_number_lookup_hash);
            $this->assertSame($identities[$index], Crypt::decryptString((string) $fresh->identity_number_ciphertext));
        }
    }

    public function test_rejects_production_without_force(): void
    {
        $previous = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->artisan('security:reencrypt-app-data')
                ->assertFailed()
                ->expectsOutputToContain('Refusing to run in production without --force');
        } finally {
            $this->app['env'] = $previous;
        }
    }

    public function test_production_with_force_and_dry_run_writes_nothing(): void
    {
        $identity = $this->generateValidNationalId();
        $user = $this->createUserWithOldCiphertext($identity);
        $before = (string) $user->identity_number_ciphertext;

        $this->bindAppKeys($this->newAppKey, [$this->oldAppKey]);

        $previous = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->artisan('security:reencrypt-app-data', [
                '--force' => true,
                '--dry-run' => true,
            ])->assertSuccessful();
        } finally {
            $this->app['env'] = $previous;
        }

        $this->assertSame($before, (string) $user->fresh()->identity_number_ciphertext);
        $this->assertSame(0, DB::table('users')->where('identity_number_ciphertext', '!=', $before)->where('id', $user->id)->count());
    }

    private function createUserWithOldCiphertext(string $identity): User
    {
        $ciphertext = $this->encryptWithKey($this->oldAppKey, $identity);
        $lookup = hash_hmac('sha256', $identity, IdentityNumberService::lookupKey());

        return User::factory()->create([
            'identity_type' => IdentityType::NationalId->value,
            'identity_number_ciphertext' => $ciphertext,
            'identity_number_lookup_hash' => $lookup,
            'identity_number_last4' => substr($identity, -4),
            'identity_confirmed_at' => now(),
        ]);
    }

    private function bindAppKeys(string $current, array $previous): void
    {
        config([
            'app.key' => $current,
            'app.previous_keys' => array_values($previous),
        ]);

        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstances();
    }

    private function makeAppKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    private function encryptWithKey(string $appKey, string $plaintext): string
    {
        return $this->encrypterForKey($appKey)->encryptString($plaintext);
    }

    private function encrypterForKey(string $appKey): Encrypter
    {
        $raw = str_starts_with($appKey, 'base64:')
            ? base64_decode(substr($appKey, 7), true)
            : $appKey;

        $this->assertIsString($raw);
        $this->assertNotFalse($raw);

        return new Encrypter($raw, 'AES-256-CBC');
    }
}
