<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_pool_consent_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 50)->unique();
            $table->string('title');
            $table->longText('content');
            $table->string('content_hash', 64);
            $table->string('status', 20)->default('draft');
            $table->boolean('requires_reconsent')->default(false);
            $table->timestamp('effective_at');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('candidate_pool_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('current_status', 32)->default('undecided');
            $table->foreignId('current_consent_version_id')->nullable()->constrained('candidate_pool_consent_versions')->nullOnDelete();
            $table->timestamp('prompted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('latest_event_id')->nullable();
            $table->timestamps();

            $table->index('current_status');
        });

        Schema::create('candidate_pool_consent_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_pool_consent_version_id')->constrained('candidate_pool_consent_versions')->restrictOnDelete();
            $table->string('event_type', 32);
            $table->text('consent_text_snapshot');
            $table->string('consent_content_hash', 64);
            $table->string('source', 40);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'candidate_pool_consent_version_id']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_pool_consent_events');
        Schema::dropIfExists('candidate_pool_preferences');
        Schema::dropIfExists('candidate_pool_consent_versions');
    }
};
