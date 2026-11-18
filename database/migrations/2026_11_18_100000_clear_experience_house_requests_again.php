<?php

use App\Services\Documents\PrivateDocumentsStorage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * حذف طلبات بيوت الخبرة التجريبية قبل الإطلاق (بطلب لمى — 18 نوفمبر 2026، الدفعة الثانية).
 */
return new class extends Migration
{
    public function up(): void
    {
        $paths = DB::table('experience_house_requests')->whereNotNull('attachment_path')->pluck('attachment_path');

        DB::table('experience_house_requests')->delete();

        foreach ($paths as $path) {
            try {
                PrivateDocumentsStorage::disk()->delete($path);
            } catch (Throwable) {
                // المرفق غير موجود أو التخزين غير متاح — لا يوقف النشر
            }
        }
    }

    public function down(): void
    {
        // لا يمكن استرجاع الطلبات المحذوفة
    }
};
