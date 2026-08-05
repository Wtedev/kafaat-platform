<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive support inbox UX foundations:
 * - Internal staff notes (never shown to beneficiaries / never emailed)
 * - Partial unique index: at most one openish ticket per authenticated user (PG)
 *
 * Does not delete tickets or messages. If duplicate openish rows already exist,
 * the unique index is skipped and logged so operators can close extras safely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ticket_internal_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['support_ticket_id', 'created_at']);
        });

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        $duplicates = DB::select(<<<'SQL'
            SELECT user_id, COUNT(*) AS c
            FROM support_tickets
            WHERE user_id IS NOT NULL
              AND status IN ('open', 'in_progress', 'waiting_on_user')
            GROUP BY user_id
            HAVING COUNT(*) > 1
            SQL);

        if ($duplicates !== []) {
            logger()->warning('support.one_open_index_skipped_due_to_duplicates', [
                'duplicate_user_count' => count($duplicates),
            ]);

            return;
        }

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX support_tickets_one_open_per_user_uidx
            ON support_tickets (user_id)
            WHERE user_id IS NOT NULL
              AND status IN ('open', 'in_progress', 'waiting_on_user')
            SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS support_tickets_one_open_per_user_uidx');
        }

        Schema::dropIfExists('support_ticket_internal_notes');
    }
};
