<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 32);
            $table->string('disk', 64);
            $table->string('path', 512);
            $table->string('mime_type', 127);
            $table->string('extension', 16);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256_checksum', 64);
            $table->string('status', 32)->default('active');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'document_type', 'status']);
            $table->index('status');
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->foreignId('current_cv_document_id')
                ->nullable()
                ->after('cv_path')
                ->constrained('user_documents')
                ->nullOnDelete();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 32)->default('user');
            $table->string('action', 64);
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resource_type', 64)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('result', 32);
            $table->string('reason', 255)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index('action');
            $table->index('target_user_id');
            $table->index('occurred_at');
            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_cv_document_id');
        });

        Schema::dropIfExists('user_documents');
    }
};
