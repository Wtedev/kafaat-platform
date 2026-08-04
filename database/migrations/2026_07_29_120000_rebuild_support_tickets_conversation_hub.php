<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuild support tickets into a conversation hub.
 *
 * Backfill is idempotent (skips tickets that already have an initial message).
 * ticket_number is backfilled before the unique constraint is applied so existing
 * rows never collide under concurrent max(id)+1-style allocation.
 * down() drops new tables/columns only — does not delete legacy ticket rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            // Nullable first; unique + NOT NULL applied after safe backfill/dedupe.
            if (! Schema::hasColumn('support_tickets', 'ticket_number')) {
                $table->string('ticket_number', 32)->nullable()->after('id');
            }
            if (! Schema::hasColumn('support_tickets', 'category')) {
                $table->string('category', 64)->nullable()->index()->after('subject');
            }
            if (! Schema::hasColumn('support_tickets', 'priority')) {
                $table->string('priority', 32)->default('normal')->index()->after('status');
            }
            if (! Schema::hasColumn('support_tickets', 'assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->after('priority')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('support_tickets', 'last_message_at')) {
                $table->timestamp('last_message_at')->nullable()->index()->after('admin_notes');
            }
            if (! Schema::hasColumn('support_tickets', 'last_message_sender_type')) {
                $table->string('last_message_sender_type', 32)->nullable()->after('last_message_at');
            }
            if (! Schema::hasColumn('support_tickets', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('last_message_sender_type');
            }
            if (! Schema::hasColumn('support_tickets', 'resolution_summary')) {
                $table->text('resolution_summary')->nullable()->after('closed_at');
            }
            if (! Schema::hasColumn('support_tickets', 'related_program_id')) {
                $table->foreignId('related_program_id')->nullable()->after('page_url')->constrained('training_programs')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('support_ticket_messages')) {
            Schema::create('support_ticket_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('sender_type', 32)->index();
                $table->text('body');
                $table->boolean('is_system')->default(false);
                $table->string('source', 32)->default('conversation')->index();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->index(['support_ticket_id', 'created_at']);
                $table->index(['support_ticket_id', 'id']);
            });
        }

        if (! Schema::hasTable('support_ticket_status_events')) {
            Schema::create('support_ticket_status_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32);
                $table->string('reason', 64)->nullable();
                $table->text('status_update_text')->nullable();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('support_ticket_message_id')->nullable()->constrained('support_ticket_messages')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['support_ticket_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('support_ticket_read_cursors')) {
            Schema::create('support_ticket_read_cursors', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('last_read_message_id')->nullable();
                $table->timestamp('last_read_at')->nullable();
                $table->timestamps();

                $table->unique(['support_ticket_id', 'user_id']);
                $table->index(['user_id', 'last_read_message_id']);
            });
        }

        $this->backfillLegacyTickets();
        $this->dedupeTicketNumbers();
        $this->enforceTicketNumberUniqueNotNull();
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_read_cursors');
        Schema::dropIfExists('support_ticket_status_events');
        Schema::dropIfExists('support_ticket_messages');

        Schema::table('support_tickets', function (Blueprint $table): void {
            foreach ([
                'related_program_id',
                'assigned_to',
            ] as $fk) {
                if (Schema::hasColumn('support_tickets', $fk)) {
                    $table->dropConstrainedForeignId($fk);
                }
            }

            foreach ([
                'ticket_number',
                'category',
                'priority',
                'last_message_at',
                'last_message_sender_type',
                'closed_at',
                'resolution_summary',
            ] as $col) {
                if (Schema::hasColumn('support_tickets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function backfillLegacyTickets(): void
    {
        $tickets = DB::table('support_tickets')->orderBy('id')->get();

        foreach ($tickets as $ticket) {
            $ticketId = (int) $ticket->id;

            if (blank($ticket->ticket_number ?? null)) {
                DB::table('support_tickets')->where('id', $ticketId)->update([
                    'ticket_number' => $this->allocateUniqueTicketNumber($ticketId),
                ]);
            }

            $hasInitial = DB::table('support_ticket_messages')
                ->where('support_ticket_id', $ticketId)
                ->where('source', 'legacy_body')
                ->exists();

            $initialMessageId = null;

            if (! $hasInitial && filled($ticket->body ?? null)) {
                $initialMessageId = DB::table('support_ticket_messages')->insertGetId([
                    'support_ticket_id' => $ticketId,
                    'user_id' => $ticket->user_id,
                    'sender_type' => 'beneficiary',
                    'body' => (string) $ticket->body,
                    'is_system' => false,
                    'source' => 'legacy_body',
                    'created_at' => $ticket->created_at ?? now(),
                    'updated_at' => $ticket->updated_at ?? null,
                ]);
            }

            $hasOpenEvent = DB::table('support_ticket_status_events')
                ->where('support_ticket_id', $ticketId)
                ->where('to_status', 'open')
                ->where('reason', 'legacy_backfill')
                ->exists();

            if (! $hasOpenEvent) {
                DB::table('support_ticket_status_events')->insert([
                    'support_ticket_id' => $ticketId,
                    'from_status' => null,
                    'to_status' => 'open',
                    'reason' => 'legacy_backfill',
                    'status_update_text' => null,
                    'actor_id' => $ticket->user_id,
                    'support_ticket_message_id' => $initialMessageId,
                    'created_at' => $ticket->created_at ?? now(),
                ]);
            }

            $status = (string) ($ticket->status ?? 'open');
            if ($status !== 'open') {
                $hasStatusEvent = DB::table('support_ticket_status_events')
                    ->where('support_ticket_id', $ticketId)
                    ->where('to_status', $status)
                    ->where('reason', 'legacy_backfill')
                    ->exists();

                if (! $hasStatusEvent) {
                    DB::table('support_ticket_status_events')->insert([
                        'support_ticket_id' => $ticketId,
                        'from_status' => 'open',
                        'to_status' => $status,
                        'reason' => 'legacy_backfill',
                        'status_update_text' => null,
                        'actor_id' => null,
                        'support_ticket_message_id' => null,
                        'created_at' => $ticket->updated_at ?? $ticket->created_at ?? now(),
                    ]);
                }
            }

            // Preserve admin_notes historically — never convert into beneficiary-visible messages.
            // Optional staff-only system marker so history is discoverable in admin UI.
            if (filled($ticket->admin_notes ?? null)) {
                $hasNotesMarker = DB::table('support_ticket_messages')
                    ->where('support_ticket_id', $ticketId)
                    ->where('source', 'legacy_admin_notes_marker')
                    ->exists();

                if (! $hasNotesMarker) {
                    DB::table('support_ticket_messages')->insert([
                        'support_ticket_id' => $ticketId,
                        'user_id' => null,
                        'sender_type' => 'system',
                        'body' => 'ملاحظة داخلية قديمة محفوظة في سجل التذكرة (غير مرئية للمستفيد).',
                        'is_system' => true,
                        'source' => 'legacy_admin_notes_marker',
                        'created_at' => $ticket->updated_at ?? $ticket->created_at ?? now(),
                        'updated_at' => null,
                    ]);
                }
            }

            $lastMessage = DB::table('support_ticket_messages')
                ->where('support_ticket_id', $ticketId)
                ->where('source', '!=', 'legacy_admin_notes_marker')
                ->orderByDesc('id')
                ->first();

            DB::table('support_tickets')->where('id', $ticketId)->update([
                'category' => $ticket->category ?? 'general',
                'priority' => $ticket->priority ?? 'normal',
                'last_message_at' => $lastMessage->created_at ?? $ticket->created_at ?? now(),
                'last_message_sender_type' => $lastMessage->sender_type ?? 'beneficiary',
                'closed_at' => $status === 'closed' ? ($ticket->updated_at ?? now()) : ($ticket->closed_at ?? null),
            ]);
        }
    }

    /**
     * Prefer ST-{id}; if that display number is already taken by another row, bump.
     */
    private function allocateUniqueTicketNumber(int $ticketId): string
    {
        $candidate = sprintf('ST-%06d', $ticketId);
        $n = $ticketId;

        while (
            DB::table('support_tickets')
                ->where('ticket_number', $candidate)
                ->where('id', '!=', $ticketId)
                ->exists()
        ) {
            $n++;
            $candidate = sprintf('ST-%06d', $n);
        }

        return $candidate;
    }

    private function dedupeTicketNumbers(): void
    {
        $duplicates = DB::table('support_tickets')
            ->select('ticket_number')
            ->whereNotNull('ticket_number')
            ->groupBy('ticket_number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('ticket_number');

        foreach ($duplicates as $ticketNumber) {
            $ids = DB::table('support_tickets')
                ->where('ticket_number', $ticketNumber)
                ->orderBy('id')
                ->pluck('id');

            // Keep the earliest row; reassign the rest.
            foreach ($ids->slice(1) as $id) {
                DB::table('support_tickets')->where('id', $id)->update([
                    'ticket_number' => $this->allocateUniqueTicketNumber((int) $id),
                ]);
            }
        }
    }

    private function enforceTicketNumberUniqueNotNull(): void
    {
        // Any remaining nulls (should not happen after backfill).
        $nullIds = DB::table('support_tickets')
            ->whereNull('ticket_number')
            ->orderBy('id')
            ->pluck('id');

        foreach ($nullIds as $id) {
            DB::table('support_tickets')->where('id', $id)->update([
                'ticket_number' => $this->allocateUniqueTicketNumber((int) $id),
            ]);
        }

        if (! $this->ticketNumberHasUniqueIndex()) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->unique('ticket_number');
            });
        }

        // NOT NULL after uniqueness is guaranteed.
        if ($this->ticketNumberIsNullable()) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->string('ticket_number', 32)->nullable(false)->change();
            });
        }
    }

    private function ticketNumberHasUniqueIndex(): bool
    {
        foreach (Schema::getIndexes('support_tickets') as $index) {
            $columns = $index['columns'] ?? [];
            if (($index['unique'] ?? false) && $columns === ['ticket_number']) {
                return true;
            }
        }

        return false;
    }

    private function ticketNumberIsNullable(): bool
    {
        foreach (Schema::getColumns('support_tickets') as $column) {
            if (($column['name'] ?? null) === 'ticket_number') {
                return (bool) ($column['nullable'] ?? true);
            }
        }

        return true;
    }
};
