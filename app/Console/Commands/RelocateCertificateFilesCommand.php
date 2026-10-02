<?php

namespace App\Console\Commands;

use App\Support\Certificates\CertificateStoredFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RelocateCertificateFilesCommand extends Command
{
    protected $signature = 'certificates:relocate-private';

    protected $description = 'ينقل ملفات PDF الشهادات من القرص العام إلى القرص الخاص ويحذف النسخة العامة';

    public function handle(): int
    {
        $moved = 0;
        foreach (Storage::disk('public')->allFiles('certificates') as $path) {
            if (! str_ends_with(strtolower($path), '.pdf')) {
                continue;
            }

            $bytes = Storage::disk('public')->get($path);
            if (! is_string($bytes) || $bytes === '') {
                continue;
            }

            CertificateStoredFile::put($path, $bytes);
            Storage::disk('public')->delete($path);
            $moved++;
        }

        $this->info('نُقل '.$moved.' ملف شهادة إلى القرص الخاص.');

        return self::SUCCESS;
    }
}
