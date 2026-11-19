<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tie program emails (acceptance, rejection, received) to their program and
 * registration so the program's messages tab can list them as they go out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table): void {
            $table->foreignId('training_program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('program_registration_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['training_program_id', 'template_key', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table): void {
            $table->dropIndex(['training_program_id', 'template_key', 'sent_at']);
            $table->dropConstrainedForeignId('program_registration_id');
            $table->dropConstrainedForeignId('training_program_id');
        });
    }
};
