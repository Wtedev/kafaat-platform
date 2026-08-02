<?php

namespace Database\Seeders;

use App\Enums\ProgramStatus;
use App\Models\TrainingProgram;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Archives «فائقون وفائقات» so it stays off public listings after Railway deploys.
 *
 * SoftDeletes are not used on training_programs; hard delete would cascade
 * program_registrations and can fail on restrictOnDelete relations (broadcasts).
 * Archive + clear published_at → public list hides it; show URL returns 404.
 * Safe to re-run (idempotent).
 */
class FaeqoonProgramArchiveSeeder extends Seeder
{
    public const TITLE_NEEDLE = 'فائقون';

    public const TITLE_NEEDLE_SECONDARY = 'فائقات';

    public const SLUG = 'faykon-ofaykat';

    public function run(): void
    {
        if (! Schema::hasTable('training_programs')) {
            $this->command?->warn('FaeqoonProgramArchiveSeeder: training_programs missing. Skipping.');

            return;
        }

        $matched = TrainingProgram::query()
            ->where(function ($query): void {
                $query->where('slug', self::SLUG)
                    ->orWhere(function ($titleQuery): void {
                        $titleQuery
                            ->where('title', 'like', '%'.self::TITLE_NEEDLE.'%')
                            ->where('title', 'like', '%'.self::TITLE_NEEDLE_SECONDARY.'%');
                    });
            })
            ->get(['id', 'title', 'slug', 'status', 'published_at']);

        if ($matched->isEmpty()) {
            $this->command?->warn(
                'FaeqoonProgramArchiveSeeder: no training program matching «'.self::TITLE_NEEDLE.' … '.self::TITLE_NEEDLE_SECONDARY.'» / slug '.self::SLUG.'.'
            );

            return;
        }

        $updated = 0;

        foreach ($matched as $program) {
            $alreadyArchived = $program->status === ProgramStatus::Archived
                && $program->published_at === null;

            if ($alreadyArchived) {
                continue;
            }

            $program->forceFill([
                'status' => ProgramStatus::Archived,
                'published_at' => null,
            ])->save();
            $updated++;
        }

        $this->command?->info(sprintf(
            'FaeqoonProgramArchiveSeeder: matched %d program(s), archived %d (slug=%s).',
            $matched->count(),
            $updated,
            self::SLUG,
        ));
    }
}
