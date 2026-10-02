<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredCertificateExportsCommand extends Command
{
    protected $signature = 'certificates:purge-expired-exports';

    protected $description = 'يحذف ملفات ZIP المنتهية لتصدير الشهادات بعد 24 ساعة';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        if (! $disk->exists('certificate-exports')) {
            $this->info('لا توجد تصديرات.');

            return self::SUCCESS;
        }

        $cutoff = now()->subHours(24)->getTimestamp();
        $deleted = 0;

        foreach ($disk->allFiles('certificate-exports') as $file) {
            if ($disk->lastModified($file) >= $cutoff) {
                continue;
            }

            $disk->delete($file);
            $deleted++;
        }

        $this->info('حُذف '.$deleted.' ملف.');

        return self::SUCCESS;
    }
}
