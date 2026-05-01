<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('job_title')->nullable()->after('city');
            $table->json('cv_sections_visibility')->nullable()->after('cv_sections');
            $table->string('cv_language', 8)->default('ar')->after('cv_sections_visibility');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['job_title', 'cv_sections_visibility', 'cv_language']);
        });
    }
};
