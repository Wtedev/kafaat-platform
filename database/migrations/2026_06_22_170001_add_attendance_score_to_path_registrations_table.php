<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('path_registrations', function (Blueprint $table) {
            $table->decimal('attendance_percentage', 5, 2)->default(0)->after('rejected_reason');
            $table->decimal('score', 5, 2)->nullable()->after('attendance_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('path_registrations', function (Blueprint $table) {
            $table->dropColumn(['attendance_percentage', 'score']);
        });
    }
};
