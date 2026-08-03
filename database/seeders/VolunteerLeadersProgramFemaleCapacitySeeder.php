<?php

namespace Database\Seeders;

use App\Enums\ProfileGender;
use App\Models\TrainingProgram;
use App\Support\ProgramAcceptanceConditions;
use App\Support\VolunteerLeadersProgramPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Marks female seats full for «قادة التطوع» while males remain eligible.
 *
 * Ops previously closed female registration by setting acceptance genders to male-only,
 * which showed «مخصص لـ: ذكر» to women. This seeder keeps males open and stores an
 * explicit gender_capacity_full flag so the public message is capacity-accurate.
 * Safe to re-run.
 */
class VolunteerLeadersProgramFemaleCapacitySeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('training_programs')) {
            $this->command?->warn('VolunteerLeadersProgramFemaleCapacitySeeder: training_programs missing. Skipping.');

            return;
        }

        $matched = TrainingProgram::query()
            ->where(function ($query): void {
                $query->whereIn('slug', VolunteerLeadersProgramPeriod::stableSlugs())
                    ->orWhere('title', 'like', '%'.VolunteerLeadersProgramPeriod::TITLE_NEEDLE.'%');
            })
            ->get(['id', 'title', 'slug', 'acceptance_conditions', 'auto_accept_registrations']);

        if ($matched->isEmpty()) {
            $this->command?->warn(
                'VolunteerLeadersProgramFemaleCapacitySeeder: no Volunteer Leaders program matched.'
            );

            return;
        }

        $updated = 0;

        foreach ($matched as $program) {
            $existing = is_array($program->acceptance_conditions) ? $program->acceptance_conditions : null;
            $normalized = ProgramAcceptanceConditions::normalize($existing) ?? [
                'require_saudi_national' => false,
                'genders' => [],
                'gender_capacity_full' => [],
                'min_age' => null,
                'max_age' => null,
                'cities' => [],
                'require_complete_profile' => false,
            ];

            // Mixed program: do not keep a male-only genders gate. Block females via capacity flag.
            $desiredGenders = [];
            $desiredCapacityFull = [ProfileGender::Female->value];

            if (
                $normalized['genders'] === $desiredGenders
                && $normalized['gender_capacity_full'] === $desiredCapacityFull
            ) {
                continue;
            }

            $normalized['genders'] = $desiredGenders;
            $normalized['gender_capacity_full'] = $desiredCapacityFull;

            // Ensure conditions stay visible/active under auto-accept or manual review.
            $packed = ProgramAcceptanceConditions::applyFormData([
                'auto_accept_registrations' => (bool) $program->auto_accept_registrations,
                'acceptance_manual_review' => true,
                'acceptance_require_saudi_national' => $normalized['require_saudi_national'],
                'acceptance_genders' => $normalized['genders'],
                'acceptance_gender_capacity_full' => $normalized['gender_capacity_full'],
                'acceptance_min_age' => $normalized['min_age'],
                'acceptance_max_age' => $normalized['max_age'],
                'acceptance_cities' => $normalized['cities'],
                'acceptance_require_complete_profile' => $normalized['require_complete_profile'],
            ]);

            $program->forceFill([
                'acceptance_conditions' => $packed['acceptance_conditions'],
            ])->save();
            $updated++;
        }

        $this->command?->info(sprintf(
            'VolunteerLeadersProgramFemaleCapacitySeeder: matched %d program(s), updated %d (female capacity full).',
            $matched->count(),
            $updated,
        ));
    }
}
