<?php

namespace App\Filament\Resources\PrivacyRequestResource\Pages;

use App\Enums\PrivacyRequestStatus;
use App\Filament\Resources\Pages\BaseViewRecord;
use App\Filament\Resources\PrivacyRequestResource;
use App\Models\DataDeletionPlan;
use App\Services\Privacy\DataDeletionPlanService;
use App\Services\Privacy\PrivacyRequestService;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewPrivacyRequest extends BaseViewRecord
{
    protected static string $resource = PrivacyRequestResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الطلب')->schema([
                TextEntry::make('uuid')->label('المرجع'),
                TextEntry::make('request_type')->label('النوع'),
                TextEntry::make('status')->label('الحالة'),
                TextEntry::make('identity_verified_at')->label('تاريخ التحقق')->dateTime('Y-m-d H:i'),
                TextEntry::make('created_at')->label('تاريخ الإنشاء')->dateTime('Y-m-d H:i'),
            ])->columns(2),
            Section::make('الأحداث')->schema([
                RepeatableEntry::make('events')
                    ->label('')
                    ->schema([
                        TextEntry::make('event')->label('الحدث'),
                        TextEntry::make('to_status')->label('الحالة'),
                        TextEntry::make('user_visible_message')->label('رسالة للمستخدم'),
                        TextEntry::make('occurred_at')->label('الوقت')->dateTime('Y-m-d H:i'),
                    ]),
            ]),
        ]);
    }

    protected function getViewPageToolbarActions(): array
    {
        return [
            Action::make('start_review')
                ->label('بدء المراجعة')
                ->visible(fn (): bool => auth()->user()?->can('privacy_requests.review')
                    && $this->getRecord()->status === PrivacyRequestStatus::Submitted)
                ->action(function (): void {
                    app(PrivacyRequestService::class)->startReview($this->getRecord(), auth()->user());
                    $this->refreshFormData(['status']);
                }),
            Action::make('approve')
                ->label('اعتماد الطلب')
                ->visible(fn (): bool => auth()->user()?->can('privacy_requests.approve')
                    && in_array($this->getRecord()->status, [PrivacyRequestStatus::Submitted, PrivacyRequestStatus::UnderReview], true))
                ->action(function (): void {
                    app(PrivacyRequestService::class)->approve($this->getRecord(), auth()->user());
                    $this->refreshFormData(['status']);
                }),
            Action::make('create_deletion_plan')
                ->label('إنشاء خطة الحذف')
                ->visible(fn (): bool => auth()->user()?->can('privacy_requests.review')
                    && $this->getRecord()->status === PrivacyRequestStatus::Approved
                    && $this->getRecord()->deletionPlan === null)
                ->action(function (): void {
                    app(DataDeletionPlanService::class)->createDraft($this->getRecord(), auth()->user());
                    Notification::make()->title('تم إنشاء خطة الحذف')->success()->send();
                }),
            Action::make('approve_deletion_plan')
                ->label('اعتماد خطة الحذف')
                ->visible(fn (): bool => auth()->user()?->can('privacy_requests.approve')
                    && $this->getRecord()->deletionPlan !== null
                    && $this->getRecord()->deletionPlan->status->value === 'ready_for_review')
                ->action(function (): void {
                    $plan = $this->getRecord()->deletionPlan;
                    if ($plan instanceof DataDeletionPlan) {
                        app(DataDeletionPlanService::class)->approve($plan, auth()->user());
                        Notification::make()->title('تم اعتماد خطة الحذف')->success()->send();
                    }
                }),
        ];
    }
}
