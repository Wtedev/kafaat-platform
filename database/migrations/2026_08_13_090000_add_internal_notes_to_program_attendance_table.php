<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_attendance', function (Blueprint $table): void {
            $table->text('internal_notes')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('program_attendance', function (Blueprint $table): void {
            $table->dropColumn('internal_notes');
        });
    }
};
