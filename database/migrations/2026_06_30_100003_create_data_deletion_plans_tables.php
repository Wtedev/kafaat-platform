<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_deletion_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('privacy_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 32);
            $table->json('plan_snapshot');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('execution_started_at')->nullable();
            $table->timestamp('execution_completed_at')->nullable();
            $table->text('failure_summary')->nullable();
            $table->timestamps();

            $table->index(['privacy_request_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('data_deletion_plan_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('data_deletion_plan_id')->constrained()->cascadeOnDelete();
            $table->string('handler', 64);
            $table->string('status', 32);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('failure_code', 64)->nullable();
            $table->timestamps();

            $table->unique(['data_deletion_plan_id', 'handler']);
        });

        Schema::create('privacy_export_files', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('privacy_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('disk', 64);
            $table->string('path');
            $table->string('format', 16)->default('zip');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('sha256_checksum', 64)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->string('status', 32);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_export_files');
        Schema::dropIfExists('data_deletion_plan_steps');
        Schema::dropIfExists('data_deletion_plans');
    }
};
