<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained('training_programs')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject', 255);
            $table->text('content');
            $table->string('audience_mode', 32)->default('statuses');
            $table->json('audience_statuses')->nullable();
            $table->string('status', 32)->default('draft');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->timestamp('sending_started_at')->nullable();
            $table->timestamp('sending_completed_at')->nullable();
            $table->timestamps();

            $table->index(['training_program_id', 'status']);
            $table->index(['training_program_id', 'created_at']);
            $table->index('created_by');
        });

        Schema::create('program_broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_broadcast_id')->constrained('program_broadcasts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('program_registration_id')->nullable()->constrained('program_registrations')->nullOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('registration_status', 32)->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('provider_message_id')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['program_broadcast_id', 'user_id'], 'pbr_broadcast_user_unique');
            $table->index(['program_broadcast_id', 'status'], 'pbr_broadcast_status_idx');
            $table->index(['program_broadcast_id', 'email'], 'pbr_broadcast_email_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_broadcast_recipients');
        Schema::dropIfExists('program_broadcasts');
    }
};
