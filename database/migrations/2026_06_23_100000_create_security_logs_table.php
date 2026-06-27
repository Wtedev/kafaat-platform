<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 120);
            $table->string('result', 32);
            $table->string('severity', 16)->default('info');
            $table->string('request_id', 64)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('identifier_hash', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index('event');
            $table->index('result');
            $table->index('severity');
            $table->index('occurred_at');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_logs');
    }
};
