<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmailNormalizationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lower_email_unique_index_exists_after_migrations(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $exists = DB::selectOne(
                "SELECT 1 AS present FROM pg_indexes WHERE schemaname = current_schema() AND indexname = 'users_email_lower_unique'"
            );
            $this->assertNotNull($exists);

            return;
        }

        if ($driver === 'sqlite') {
            $exists = DB::selectOne(
                "SELECT 1 AS present FROM sqlite_master WHERE type = 'index' AND name = 'users_email_lower_unique'"
            );
            $this->assertNotNull($exists);

            return;
        }

        $this->markTestSkipped('lower(email) unique index is created on pgsql/sqlite.');
    }

    public function test_raw_insert_of_case_variant_email_is_rejected(): void
    {
        if (! in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            $this->markTestSkipped('Case-insensitive unique index is enforced on pgsql/sqlite.');
        }

        $user = User::factory()->create([
            'email' => 'index.lock@example.com',
            'password' => Hash::make('SecretPass1!'),
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('users')->insert([
            'name' => 'Case Variant',
            'email' => 'INDEX.LOCK@EXAMPLE.COM',
            'password' => Hash::make('OtherPass1!'),
            'role_type' => 'beneficiary',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('index.lock@example.com', $user->fresh()->email);
    }
}
