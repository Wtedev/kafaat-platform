<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's $table->json() maps to PostgreSQL "json", not "jsonb".
 * Convert listed application JSON columns to jsonb on pgsql only.
 *
 * No GIN indexes: app code uses Eloquent array casts / PHP filtering,
 * not JSONB containment or path queries (no whereJson* usage found).
 */
return new class extends Migration
{
    /**
     * table => columns
     *
     * @var array<string, list<string>>
     */
    private array $columns = [
        'training_programs' => [
            'weekdays',
            'session_topics',
            'acceptance_conditions',
            'program_presenters',
        ],
        'users' => [
            'notification_settings',
        ],
        'security_logs' => [
            'metadata',
        ],
        'audit_logs' => [
            'metadata',
        ],
        'privacy_request_events' => [
            'metadata',
        ],
        'data_deletion_plans' => [
            'plan_snapshot',
        ],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->columns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::statement(sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s TYPE jsonb USING %s::jsonb',
                    $table,
                    $column,
                    $column
                ));
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->columns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::statement(sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s TYPE json USING %s::json',
                    $table,
                    $column,
                    $column
                ));
            }
        }
    }
};
