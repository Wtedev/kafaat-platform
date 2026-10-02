<?php

namespace App\Console\Commands;

use App\Models\TrainingProgram;
use App\Services\Certificates\CertificateRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RenderSampleCertificateCommand extends Command
{
    protected $signature = 'certificates:render-sample {program : معرف البرنامج} {--output=}';

    protected $description = 'يرسم شهادة تجريبية من قالب البرنامج المحفوظ للمقارنة مع معاينة صفحة التصميم';

    public function handle(CertificateRenderer $renderer): int
    {
        $program = TrainingProgram::query()->find($this->argument('program'));
        if (! $program instanceof TrainingProgram) {
            $this->error('البرنامج غير موجود.');

            return self::FAILURE;
        }

        $template = $program->certificateTemplate;
        if ($template === null) {
            $this->error('لا يوجد قالب شهادة لهذا البرنامج. احفظ التصميم أولاً.');

            return self::FAILURE;
        }

        $binary = $renderer->render($template, $renderer->sampleValues());
        $relative = $this->option('output') ?: 'certificate-samples/program-'.$program->id.'.pdf';
        Storage::disk('local')->put($relative, $binary);
        $this->info(Storage::disk('local')->path($relative));

        return self::SUCCESS;
    }
}
