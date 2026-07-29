<?php

namespace App\Console\Commands;

use App\Services\Auth\PendingRegistrationService;
use Illuminate\Console\Command;

class PurgeExpiredPendingRegistrationsCommand extends Command
{
    protected $signature = 'auth:purge-expired-pending-registrations';

    protected $description = 'Delete expired or consumed pending registrations older than one day';

    public function handle(PendingRegistrationService $service): int
    {
        $deleted = $service->purgeExpired();
        $this->info("Purged {$deleted} pending registration(s).");

        return self::SUCCESS;
    }
}
