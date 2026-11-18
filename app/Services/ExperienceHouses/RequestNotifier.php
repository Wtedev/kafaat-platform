<?php

namespace App\Services\ExperienceHouses;

use App\Mail\ExperienceHouseNewRequestMail;
use App\Mail\ExperienceHouseStatusMail;
use App\Models\ExperienceHouseManager;
use App\Models\ExperienceHouseRequest;
use Illuminate\Support\Facades\Mail;

/**
 * إيميلات طلبات بيوت الخبرة. فشل الإرسال لا يوقف حفظ الطلب أو تحديثه.
 */
final class RequestNotifier
{
    /** إيميل لكل أعضاء الفريق النشطين بوصول طلب جديد. */
    public function notifyTeam(ExperienceHouseRequest $request): void
    {
        ExperienceHouseManager::query()->where('is_active', true)->get()
            ->each(function (ExperienceHouseManager $manager) use ($request): void {
                try {
                    Mail::to($manager->email)->send(new ExperienceHouseNewRequestMail($request, $manager));
                } catch (\Throwable $e) {
                    report($e);
                }
            });
    }

    /** إيميل للجمعية بتحديث حالة طلبها. */
    public function notifyApplicant(ExperienceHouseRequest $request): void
    {
        if (! $request->contact_email || ! ExperienceHouseStatusMail::supports($request->status)) {
            return;
        }

        try {
            Mail::to($request->contact_email)->send(new ExperienceHouseStatusMail($request));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
