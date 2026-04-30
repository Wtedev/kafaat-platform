<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_email');
            $table->string('subject');
            $table->string('template_key')->nullable();
            $table->string('status')->default('sent');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('recipient_email');
            $table->index('template_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
