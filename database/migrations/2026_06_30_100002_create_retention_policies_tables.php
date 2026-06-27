<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('resource_type', 64);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_event', 64);
            $table->unsignedInteger('retention_period_days')->nullable();
            $table->unsignedInteger('grace_period_days')->default(0);
            $table->string('action', 32);
            $table->boolean('enabled')->default(false);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_at')->nullable();
            $table->timestamps();

            $table->index(['resource_type', 'enabled']);
        });

        Schema::create('retention_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('resource_type', 64);
            $table->unsignedBigInteger('resource_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason_code', 64);
            $table->text('reason');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['resource_type', 'resource_id']);
            $table->index(['user_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_exceptions');
        Schema::dropIfExists('retention_policies');
    }
};
