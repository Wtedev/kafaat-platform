<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('iconic_skill')->nullable()->after('avatar');
            $table->json('competency_levels')->nullable()->after('iconic_skill');
            $table->string('cv_path')->nullable()->after('competency_levels');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['iconic_skill', 'competency_levels', 'cv_path']);
        });
    }
};
