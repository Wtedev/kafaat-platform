<?php

namespace Database\Seeders;

use App\Enums\ProgramPrepDayType;
use App\Models\ProgramAttendance;
use App\Models\ProgramPrepDay;
use App\Models\TrainingProgram;
use App\Support\VolunteerLeadersProgramPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures «قادة التطوع» official prep calendar exists (idempotent).
 * Matches by stable slug/aliases — not mutable title.
 *
 * Period 2026-08-03 → 2026-09-01 inclusive (30 days):
 * - 6 in-person dates from VolunteerLeadersProgramPeriod::IN_PERSON_DATES
 * - remaining days remote
 * - every day requires_attendance=true
 *
 * Does not modify program_attendance rows. Safe to re-run
 * (whereDate upsert on training_program_id + prep_date + prune outside period).
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

        $periodDates = VolunteerLeadersProgramPeriod::periodDates();
        $created = 0;
        $updated = 0;
        $pruned = 0;
        $attendanceBefore = Schema::hasTable('program_attendance')
            ? ProgramAttendance::query()->count()
            : null;

        foreach ($matched as $program) {
            foreach ($periodDates as $date) {
                $delivery = VolunteerLeadersProgramPeriod::isInPersonDate($date)
                    ? ProgramPrepDayType::InPerson
                    : ProgramPrepDayType::Remote;

                $day = ProgramPrepDay::query()
                    ->where('training_program_id', $program->id)
                    ->whereDate('prep_date', $date)
                    ->first();

                if ($day === null) {
                    ProgramPrepDay::query()->create([
                        'training_program_id' => $program->id,
                        'prep_date' => $date,
                        'delivery_type' => $delivery,
                        'requires_attendance' => true,
                    ]);
                    $created++;

                    continue;
                }

                $day->fill([
                    'delivery_type' => $delivery,
                    'requires_attendance' => true,
                ]);

                if ($day->isDirty()) {
                    $day->save();
                    $updated++;
                }
            }

            $pruned += ProgramPrepDay::query()
                ->where('training_program_id', $program->id)
                ->where(function ($query): void {
                    $query->whereDate('prep_date', '<', VolunteerLeadersProgramPeriod::PERIOD_START)
                        ->orWhereDate('prep_date', '>', VolunteerLeadersProgramPeriod::PERIOD_END);
                })
                ->delete();
        }

        if ($attendanceBefore !== null) {
            $attendanceAfter = ProgramAttendance::query()->count();
            if ($attendanceAfter !== $attendanceBefore) {
                $this->command?->error(sprintf(
                    'VolunteerLeadersProgramPrepDaysSeeder: unexpected program_attendance count change (%d → %d).',
                    $attendanceBefore,
                    $attendanceAfter,
                ));
            }
        }

        $this->command?->info(sprintf(
            'VolunteerLeadersProgramPrepDaysSeeder: %d matched, %d new, %d updated, %d pruned outside period (%d official days).',
            $matched->count(),
            $created,
            $updated,
            $pruned,
            count($periodDates),
        ));
    }
}
