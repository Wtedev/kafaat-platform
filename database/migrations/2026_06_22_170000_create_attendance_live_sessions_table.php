<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_live_sessions', function (Blueprint $table) {
            $table->id();
            $table->morphs('attendable');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['attendable_type', 'attendable_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_live_sessions');
    }
};
