<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_page_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('status_code');
            $table->string('requested_url', 2048);
            $table->string('route_name')->nullable();
            $table->string('request_method', 16);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('referer', 2048)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('exception_class')->nullable();
            $table->timestamps();

            $table->index('status_code');
            $table->index('created_at');
            $table->index('user_id');
            $table->index(['status_code', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_page_visits');
    }
};
