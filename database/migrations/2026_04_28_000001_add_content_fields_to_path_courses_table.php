<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('path_courses', function (Blueprint $table) {
            $table->longText('content')->nullable()->after('description');
            $table->string('video_url')->nullable()->after('content');
            $table->unsignedInteger('duration_minutes')->nullable()->after('video_url');
            $table->boolean('is_required')->default(true)->after('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('path_courses', function (Blueprint $table) {
            $table->dropColumn(['content', 'video_url', 'duration_minutes', 'is_required']);
        });
    }
};
