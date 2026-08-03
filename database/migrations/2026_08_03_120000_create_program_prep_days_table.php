<?php

use App\Support\VolunteerLeadersProgramPeriod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_prep_days', function (Blueprint $table) {
            $table->id();

            $table->foreignId('training_program_id')
                ->constrained('training_programs')
                ->cascadeOnDelete();

            $table->date('prep_date');

            // ProgramPrepDayType: in_person | remote
            $table->string('delivery_type', 20);

            // Remote days default false unless flagged «يتطلب تحضير»
            $table->boolean('requires_attendance')->default(false);

            $table->timestamps();

            $table->unique(['training_program_id', 'prep_date']);
            $table->index(
                ['training_program_id', 'requires_attendance', 'prep_date'],
                'program_prep_days_program_requires_date_idx',
            );
        });

        $this->backfillVolunteerLeadersInPersonDays();
    }

    public function down(): void
    {
        Schema::dropIfExists('program_prep_days');
    }

    private function backfillVolunteerLeadersInPersonDays(): void
    {
        if (! Schema::hasTable('training_programs')) {
            return;
        }

        $now = now();
        $slugs = VolunteerLeadersProgramPeriod::stableSlugs();

        $programIds = DB::table('training_programs')
            ->whereIn('slug', $slugs)
            ->pluck('id');

        foreach ($programIds as $programId) {
            foreach (VolunteerLeadersProgramPeriod::IN_PERSON_DATES as $date) {
                $exists = DB::table('program_prep_days')
                    ->where('training_program_id', $programId)
                    ->whereDate('prep_date', $date)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('program_prep_days')->insert([
                    'training_program_id' => $programId,
                    'prep_date' => $date,
                    'delivery_type' => 'in_person',
                    'requires_attendance' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
