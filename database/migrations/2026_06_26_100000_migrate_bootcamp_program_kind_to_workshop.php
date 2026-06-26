<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('training_programs')
            ->where('program_kind', 'bootcamp')
            ->update(['program_kind' => 'workshop']);
    }

    public function down(): void
    {
        // لا نعيد bootcamp — النوع أُزيل من TrainingProgramKind نهائياً.
    }
};
