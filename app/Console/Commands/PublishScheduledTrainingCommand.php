<?php

namespace App\Console\Commands;

use App\Enums\PathStatus;
use App\Enums\ProgramStatus;
use App\Models\LearningPath;
use App\Models\TrainingProgram;
use Illuminate\Console\Command;

class PublishScheduledTrainingCommand extends Command
{
    protected $signature = 'training:publish-scheduled';

    protected $description = 'ينشر البرامج والمسارات المجدولة التي حلّ موعد نشرها.';

    public function handle(): int
    {
        $now = now();
        $programs = 0;
        $paths = 0;

        TrainingProgram::query()
            ->where('status', ProgramStatus::Draft)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$programs): void {
                foreach ($chunk as $program) {
                    $program->update(['status' => ProgramStatus::Published]);
                    $programs++;
                }
            });

        LearningPath::query()
            ->where('status', PathStatus::Draft)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$paths): void {
                foreach ($chunk as $path) {
                    $path->update(['status' => PathStatus::Published]);
                    $paths++;
                }
            });

        $this->info("Published {$programs} program(s) and {$paths} path(s).");

        return self::SUCCESS;
    }
}
