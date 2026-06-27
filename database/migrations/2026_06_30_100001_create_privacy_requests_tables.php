<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('request_type', 32);
            $table->string('status', 32);
            $table->json('request_details')->nullable();
            $table->string('identity_verification_method', 32)->nullable();
            $table->timestamp('identity_verified_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->text('decision_summary')->nullable();
            $table->string('rejection_reason_code', 64)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'request_type', 'status']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('privacy_request_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('privacy_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 32)->default('user');
            $table->string('event', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('internal_comment')->nullable();
            $table->text('user_visible_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['privacy_request_id', 'occurred_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('deletion_request_id')
                ->nullable()
                ->after('anonymized_at')
                ->constrained('privacy_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deletion_request_id');
        });

        Schema::dropIfExists('privacy_request_events');
        Schema::dropIfExists('privacy_requests');
    }
};
