<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_policy_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 50)->unique();
            $table->string('title');
            $table->longText('content');
            $table->string('content_hash', 64);
            $table->timestamp('effective_at');
            $table->timestamp('published_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('requires_reacknowledgement')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index(['status', 'effective_at']);
        });

        Schema::create('privacy_policy_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('privacy_policy_version_id')->constrained('privacy_policy_versions')->restrictOnDelete();
            $table->string('acknowledgement_text_snapshot');
            $table->string('policy_content_hash', 64);
            $table->timestamp('acknowledged_at');
            $table->string('source', 40);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'privacy_policy_version_id']);
            $table->index('privacy_policy_version_id');
            $table->index('acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_policy_acknowledgements');
        Schema::dropIfExists('privacy_policy_versions');
    }
};
