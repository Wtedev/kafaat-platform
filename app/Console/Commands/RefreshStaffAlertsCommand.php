<?php

namespace App\Console\Commands;

use App\Services\StaffAlerts\StaffAlerts;
use Illuminate\Console\Command;

class RefreshStaffAlertsCommand extends Command
{
    protected $signature = 'staff-alerts:refresh';

    protected $description = 'Recompute staff alerts from the current data (pending requests, delivery problems, reminders, support).';

    public function handle(StaffAlerts $alerts): int
    {
        $alerts->refresh();
        $this->info('Staff alerts refreshed.');

        return self::SUCCESS;
    }
}
