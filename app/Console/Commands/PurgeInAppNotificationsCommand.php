<?php

namespace App\Console\Commands;

use App\Models\InboxNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeInAppNotificationsCommand extends Command
{
    protected $signature = 'notifications:purge
                            {--force : تنفيذ الحذف دون طلب تأكيد}';

    protected $description = 'حذف جميع تنبيهات صندوق الوارد (in_app_notifications) لجميع المستخدمين — عملية لمرة واحدة';

    public function handle(): int
    {
        $count = InboxNotification::query()->count();

        if ($count === 0) {
            $this->info('لا توجد تنبيهات لحذفها.');

            return self::SUCCESS;
        }

        $this->warn("سيتم حذف {$count} تنبيهًا من جميع المستخدمين.");
        $this->line('هذا لا يحذف إعدادات التنبيهات (notify_email) ولا سجل البريد (email_logs).');

        if (! $this->option('force') && ! $this->confirm('هل تريد المتابعة؟', false)) {
            $this->info('تم الإلغاء.');

            return self::SUCCESS;
        }

        DB::table((new InboxNotification)->getTable())->delete();

        $this->info("تم حذف {$count} تنبيهًا.");

        return self::SUCCESS;
    }
}
