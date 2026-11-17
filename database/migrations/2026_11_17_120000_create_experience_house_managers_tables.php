<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_house_managers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->boolean('is_active')->default(true);
            $table->foreignId('invited_by_id')->nullable()->constrained('experience_house_managers')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('experience_house_login_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manager_id')->constrained('experience_house_managers')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('purpose', 16); // invite | login
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('experience_house_requests', function (Blueprint $table) {
            $table->foreignId('handled_by_id')->nullable()->after('staff_notes')
                ->constrained('experience_house_managers')->nullOnDelete();
            $table->timestamp('status_changed_at')->nullable()->after('handled_by_id');
        });

        // أول مسؤولة عن النظام
        DB::table('experience_house_managers')->insert([
            'name' => 'لمى المشيقح',
            'email' => 'lamaalmushyqih@gmail.com',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('experience_house_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handled_by_id');
            $table->dropColumn('status_changed_at');
        });
        Schema::dropIfExists('experience_house_login_tokens');
        Schema::dropIfExists('experience_house_managers');
    }
};
