<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->json('membership_badges')->nullable()->after('membership_type');
            $table->string('iconic_skill_style', 32)->nullable()->after('iconic_skill');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropColumn(['membership_badges', 'iconic_skill_style']);
        });
    }
};
