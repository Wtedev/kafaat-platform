<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('volunteer_opportunities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();

            $table->unique(['opportunity_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_registrations');
    }
};
