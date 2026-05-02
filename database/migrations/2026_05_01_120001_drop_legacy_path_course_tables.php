<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_course_progress');
        Schema::dropIfExists('path_courses');
    }

    public function down(): void
    {
        throw new RuntimeException('Legacy path_courses / user_course_progress tables were removed; restore from a database backup if needed.');
    }
};
