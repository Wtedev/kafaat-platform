<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use App\Models\TrainingProgram;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeTrainingProgramsCommand extends Command
{
    protected $signature = 'programs:purge
                            {--force : تنفيذ الحذف دون طلب تأكيد}';

    protected $description = 'حذف جميع البرامج التدريبية وتسجيلاتها وشهاداتها المرتبطة — عملية لمرة واحدة';

    public function handle(): int
    {
        $programCount = TrainingProgram::query()->count();

        if ($programCount === 0) {
            $this->info('لا توجد برامج لحذفها.');

            return self::SUCCESS;
        }

        $certCount = Certificate::query()
            ->where('certificateable_type', TrainingProgram::class)
            ->count();

        $this->warn("سيتم حذف {$programCount} برنامجًا تدريبيًا.");
        if ($certCount > 0) {
            $this->line("ومعه {$certCount} شهادة مرتبطة بالبرامج.");
        }
        $this->line('يُحذف أيضاً: تسجيلات البرامج، محرّرو البرنامج (pivot)، عبر قاعدة البيانات.');
        $this->line('لا يُحذف: المسارات، المستخدمون، فرص التطوع.');

        if (! $this->option('force') && ! $this->confirm('هل تريد المتابعة؟', false)) {
            $this->info('تم الإلغاء.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($certCount): void {
            if ($certCount > 0) {
                Certificate::query()
                    ->where('certificateable_type', TrainingProgram::class)
                    ->delete();
            }

            TrainingProgram::query()->delete();
        });

        $this->info("تم حذف {$programCount} برنامجًا.");

        return self::SUCCESS;
    }
}
