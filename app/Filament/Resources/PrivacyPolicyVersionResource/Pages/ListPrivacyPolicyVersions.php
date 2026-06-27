<?php

namespace App\Filament\Resources\PrivacyPolicyVersionResource\Pages;

use App\Filament\Resources\Pages\BaseListRecords;
use App\Filament\Resources\PrivacyPolicyVersionResource;
use App\Services\Privacy\PrivacyPolicyService;
use Filament\Notifications\Notification;

class ListPrivacyPolicyVersions extends BaseListRecords
{
    protected static string $resource = PrivacyPolicyVersionResource::class;

    public function mount(): void
    {
        parent::mount();

        if (PrivacyPolicyService::active() === null && auth()->user()?->can('privacy_policy.view')) {
            Notification::make()
                ->title('لا يوجد إصدار فعّال لسياسة الخصوصية')
                ->body('التسجيل العام وتأكيد الإقرار معطّلان حتى نشر إصدار فعّال.')
                ->warning()
                ->persistent()
                ->send();
        }
    }
}
