<?php

namespace App\Console\Commands;

use App\Services\Certificates\ProgramCertificateAutoIssuer;
use Illuminate\Console\Command;

class AutoIssueProgramCertificatesCommand extends Command
{
    protected $signature = 'certificates:auto-issue';

    protected $description = 'Queue certificates for every registration that meets its program\'s automatic certificate criteria.';

    public function handle(ProgramCertificateAutoIssuer $autoIssuer): int
    {
        $count = $autoIssuer->sweepAll();
        $this->info("Queued {$count} certificate(s).");

        return self::SUCCESS;
    }
}
