<?php

namespace Database\Seeders;

use App\Enums\ProgramPrepDayType;
use App\Models\ProgramPrepDay;
use App\Models\TrainingProgram;
use App\Support\VolunteerLeadersProgramPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures «قادة التطوع» in-person prep days exist (idempotent).
 * Matches by stable slug/aliases — not mutable title.
 *
 * Output «N matched, 0 new» means programs were found and all six dates
 * already existed (re-run / after migration backfill).
 */
class VolunteerLeadersProgramPrepDaysSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('program_prep_days') || ! Schema::hasTable('training_programs')) {
            $this->command?->warn('VolunteerLeadersProgramPrepDaysSeeder: required tables missing. Skipping.');

            return;
        }

        $matched = TrainingProgram::query()
            ->whereIn('slug', VolunteerLeadersProgramPeriod::stableSlugs())
            ->get(['id', 'title', 'slug']);

        if ($matched->isEmpty()) {
            $this->command?->warn(sprintf(
                'VolunteerLeadersProgramPrepDaysSeeder: no program matching slug(s) %s.',
                implode(', ', VolunteerLeadersProgramPeriod::stableSlugs()),
            ));

            return;
        }

        $created = 0;

        foreach ($matched as $program) {
            foreach (VolunteerLeadersProgramPeriod::IN_PERSON_DATES as $date) {
                $existing = ProgramPrepDay::query()
                    ->where('training_program_id', $program->id)
                    ->whereDate('prep_date', $date)
                    ->first();

                if ($existing !== null) {
                    continue;
                }

                ProgramPrepDay::query()->create([
                    'training_program_id' => $program->id,
                    'prep_date' => $date,
                    'delivery_type' => ProgramPrepDayType::InPerson,
                    'requires_attendance' => true,
                ]);
                $created++;
            }
        }

        $this->command?->info(sprintf(
            'VolunteerLeadersProgramPrepDaysSeeder: %d matched, %d new.',
            $matched->count(),
            $created,
        ));
    }
}
