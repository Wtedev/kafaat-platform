<?php

namespace Database\Seeders;

use App\Models\TrainingProgram;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Sets the durable women’s WhatsApp invite for «قادة التطوع». Safe to re-run.
 *
 * Does not overwrite the men’s invite URL.
 */
class VolunteerLeadersProgramWhatsappSeeder extends Seeder
{
    public const TITLE_NEEDLE = 'قادة التطوع';

    /** Canonical women invite («بناء قادة التطوع - نساء»). */
    public const FEMALE_URL = 'https://chat.whatsapp.com/IBurUinsNL3I7MQGPglgaM?s=cl&p=i&mlu=4';

    public function run(): void
    {
        if (! Schema::hasTable('training_programs')) {
            $this->command?->warn('VolunteerLeadersProgramWhatsappSeeder: training_programs missing. Skipping.');

            return;
        }

        if (! Schema::hasColumn('training_programs', 'whatsapp_group_female')
            || ! Schema::hasColumn('training_programs', 'whatsapp_groups_enabled')) {
            $this->command?->warn('VolunteerLeadersProgramWhatsappSeeder: whatsapp columns missing. Skipping.');

            return;
        }

        $matched = TrainingProgram::query()
            ->where('title', 'like', '%'.self::TITLE_NEEDLE.'%')
            ->get(['id', 'title', 'whatsapp_groups_enabled', 'whatsapp_group_male', 'whatsapp_group_female']);

        if ($matched->isEmpty()) {
            $this->command?->warn(
                'VolunteerLeadersProgramWhatsappSeeder: no training program title matching «'.self::TITLE_NEEDLE.'».'
            );

            return;
        }

        $updated = 0;

        foreach ($matched as $program) {
            $enabledOk = (bool) $program->whatsapp_groups_enabled === true;
            $femaleOk = trim((string) $program->whatsapp_group_female) === self::FEMALE_URL;

            if ($enabledOk && $femaleOk) {
                continue;
            }

            $program->forceFill([
                'whatsapp_groups_enabled' => true,
                'whatsapp_group_female' => self::FEMALE_URL,
            ])->save();
            $updated++;
        }

        $this->command?->info(sprintf(
            'VolunteerLeadersProgramWhatsappSeeder: matched %d program(s), updated %d (female invite set; male left unchanged).',
            $matched->count(),
            $updated,
        ));
    }
}
