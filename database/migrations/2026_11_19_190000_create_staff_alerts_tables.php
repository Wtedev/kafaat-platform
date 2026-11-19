<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff alerts: one live row per thing that needs staff attention (pending
 * requests, delivery problems, program reminders, support tickets). A row is
 * refreshed while the situation lasts and resolved when it goes away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_alerts', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('kind', 40)->index();
            $table->string('title');
            $table->string('body')->nullable();
            $table->unsignedInteger('count')->default(0);
            $table->json('payload')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamp('dismissed_at')->nullable();
            $table->foreignId('dismissed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('dismissed_count')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_alert_reads', function (Blueprint $table): void {
            $table->foreignId('staff_alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->primary(['staff_alert_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_alert_reads');
        Schema::dropIfExists('staff_alerts');
    }
};
