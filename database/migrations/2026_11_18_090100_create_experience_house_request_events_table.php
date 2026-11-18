<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_house_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('experience_house_requests')->cascadeOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('experience_house_managers')->nullOnDelete();
            $table->string('type', 16); // created | status | comment
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('body')->nullable();
            $table->timestamps();

            $table->index(['request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_house_request_events');
    }
};
