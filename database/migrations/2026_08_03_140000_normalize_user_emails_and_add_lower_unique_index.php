<?php

use App\Support\Auth\CaseInsensitiveEmailDuplicateScanner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalize stored user emails to lowercase+trim and enforce case-insensitive uniqueness.
 *
 * Aborts (does not merge/delete) if case-insensitive duplicate emails already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = CaseInsensitiveEmailDuplicateScanner::findInRows(
            DB::table('users')->select(['id', 'email'])->orderBy('id')->get()
        );

        if ($duplicates !== []) {
            $report = collect($duplicates)
                ->map(fn (array $row) => sprintf(
                    '%s (count=%s, ids=%s)',
                    $row['normalized_email'],
                    $row['aggregate'],
                    $row['ids'],
                ))
                ->implode('; ');

            throw new RuntimeException(
                'Cannot add case-insensitive email uniqueness: duplicate emails found. '.
                'Resolve manually before re-running. Duplicates: '.$report
            );
        }

        DB::table('users')
            ->select(['id', 'email'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $normalized = strtolower(trim((string) $row->email));
                    if ($normalized === (string) $row->email) {
                        continue;
                    }

                    DB::table('users')->where('id', $row->id)->update(['email' => $normalized]);
                }
            });

        if ($this->hasLowerEmailUniqueIndex()) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (lower(email))');
        }
    }

    public function down(): void
    {
        if (! $this->hasLowerEmailUniqueIndex()) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');

            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_email_lower_unique');
        });
    }

    private function hasLowerEmailUniqueIndex(): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $exists = DB::selectOne(
                "SELECT 1 AS present FROM pg_indexes WHERE schemaname = current_schema() AND indexname = 'users_email_lower_unique'"
            );

            return $exists !== null;
        }

        if ($driver === 'sqlite') {
            $exists = DB::selectOne(
                "SELECT 1 AS present FROM sqlite_master WHERE type = 'index' AND name = 'users_email_lower_unique'"
            );

            return $exists !== null;
        }

        return Schema::hasIndex('users', 'users_email_lower_unique');
    }
};
