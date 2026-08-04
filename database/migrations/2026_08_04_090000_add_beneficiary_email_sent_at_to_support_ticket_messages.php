<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency marker for staff-reply emails: set only after a successful send
 * so queue retries do not duplicate delivery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_ticket_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_ticket_messages', 'beneficiary_email_sent_at')) {
                $table->timestamp('beneficiary_email_sent_at')->nullable()->after('source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_ticket_messages', function (Blueprint $table): void {
            if (Schema::hasColumn('support_ticket_messages', 'beneficiary_email_sent_at')) {
                $table->dropColumn('beneficiary_email_sent_at');
            }
        });
    }
};
