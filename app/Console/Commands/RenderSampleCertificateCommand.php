<?php

namespace App\Console\Commands;

use App\Models\TrainingProgram;
use App\Services\CertificatePdfService;
use Illuminate\Console\Command;
use RuntimeException;

class RenderSampleCertificateCommand extends Command
{
    protected $signature = 'certificates:render-sample {program : Training program id}';

    protected $description = 'Write a sample certificate PDF from the saved program design without issuing a certificate';

    public function handle(CertificatePdfService $pdf): int
    {
        $program = TrainingProgram::query()->find($this->argument('program'));
        if (! $program instanceof TrainingProgram) {
            $this->error('Program was not found.');

            return self::FAILURE;
        }

        try {
            $path = $pdf->writeSample($program);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($path);

        return self::SUCCESS;
    }
}
