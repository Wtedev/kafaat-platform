<?php

namespace App\Filament\Resources\PrivacyRequestResource\Pages;

use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacyRequestType;
use App\Filament\Resources\Pages\BaseViewRecord;
use App\Filament\Resources\PrivacyRequestResource;
use App\Models\DataDeletionPlan;
use App\Services\Access\SensitiveAccessVerification;
use App\Services\Privacy\DataDeletionPlanService;
use App\Services\Privacy\PersonalDataDeletionService;
use App\Services\Privacy\PrivacyCorrectionService;
use App\Services\Privacy\PrivacyRequestService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ViewPrivacyRequest extends BaseViewRecord
{
    protected static string $resource = PrivacyRequestResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الطلب')->schema([
                TextEntry::make('uuid')->label('المرجع'),
                TextEntry::make('request_type')->label('النوع')->formatStateUsing(fn ($state) => $state?->label()),
                TextEntry::make('status')->label('الحالة')->formatStateUsing(fn ($state) => $state?->label()),
                TextEntry::make('user.name')->label('المستخدم'),
                TextEntry::make('assignee.name')->label('المعيَّن')->placeholder('—'),
                TextEntry::make('due_at')->label('المهلة')->dateTime('Y-m-d H:i')->placeholder('—'),
                TextEntry::make('identity_verified_at')->label('تاريخ التحقق')->dateTime('Y-m-d H:i')->placeholder('—'),
                TextEntry::make('created_at')->label('تاريخ الإنشاء')->dateTime('Y-m-d H:i'),
            ])->columns(2),
            Section::make('طلب الوصول')
                ->visible(fn (): bool => $this->getRecord()->request_type === PrivacyRequestType::DataAccess)
                ->schema([
                    TextEntry::make('user_visible_response')->label('الاستجابة للمستخدم')->placeholder('—')->columnSpanFull(),
                ]),
            Section::make('طلب التصحيح')
                ->visible(fn (): bool => $this->getRecord()->request_type === PrivacyRequestType::DataCorrection)
                ->schema([
                    TextEntry::make('correction_field_code')->label('الحقل')->formatStateUsing(
                        fn (?string $state) => \App\Enums\PrivacyCorrectionFieldCode::tryFrom((string) $state)?->label() ?? '—',
                    ),
                    TextEntry::make('request_details.reason')->label('سبب المستخدم'),
                    TextEntry::make('correctionPayload.value_last4')
                        ->label('آخر أربعة أرقام')
                        ->visible(fn (): bool => filled($this->getRecord()->correctionPayload?->value_last4))
                        ->formatStateUsing(fn (?string $state): string => $state ? '******'.$state : '—'),
                    TextEntry::make('certificates_warning')
                        ->label('تنبيه الشهادات')
                        ->state(fn (): string => app(PrivacyCorrectionService::class)->userHasCertificates($this->getRecord()->user)
                            ? 'لدى المستفيد شهادات مرتبطة. تعديل بيانات الحساب لا يعيد إصدار الشهادات تلقائياً.'
                            : 'لا توجد شهادات مرتبطة.')
                        ->columnSpanFull(),
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
            Action::make('assign_to_me')
                ->label('تعيين لي')
                ->visible(fn (): bool => auth()->user()?->can('privacy_requests.assign') === true)
                ->action(function (): void {
                    app(PrivacyRequestService::class)->assign($this->getRecord(), auth()->user(), auth()->user());
                }),
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
            Action::make('partially_approve')
                ->label('اعتماد جزئي')
                ->visible(fn (): bool => auth()->user()?->can('privacy_requests.approve')
                    && in_array($this->getRecord()->request_type, [PrivacyRequestType::DataAccess, PrivacyRequestType::DataCorrection], true)
                    && in_array($this->getRecord()->status, [PrivacyRequestStatus::Submitted, PrivacyRequestStatus::UnderReview], true))
                ->action(function (): void {
                    app(PrivacyRequestService::class)->partiallyApprove($this->getRecord(), auth()->user());
                    $this->refreshFormData(['status']);
                }),
            Action::make('reject')
                ->label('رفض الطلب')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => auth()->user()?->can('privacy_requests.reject')
                    && ! $this->getRecord()->status->isTerminal())
                ->schema([
                    TextInput::make('reason_code')->label('رمز السبب')->required(),
                    Textarea::make('reason')->label('سبب الرفض')->required(),
                ])
                ->action(function (array $data): void {
                    app(PrivacyRequestService::class)->reject(
                        $this->getRecord(),
                        auth()->user(),
                        (string) $data['reason_code'],
                        (string) $data['reason'],
                    );
                    $this->refreshFormData(['status']);
                }),
            Action::make('complete_access')
                ->label('إكمال طلب الوصول')
                ->visible(fn (): bool => auth()->user()?->can('privacy_requests.review')
                    && $this->getRecord()->request_type === PrivacyRequestType::DataAccess
                    && in_array($this->getRecord()->status, [PrivacyRequestStatus::Approved, PrivacyRequestStatus::PartiallyApproved, PrivacyRequestStatus::UnderReview], true))
                ->requiresConfirmation()
                ->action(function (): void {
                    app(PrivacyRequestService::class)->completeAccessRequest($this->getRecord(), auth()->user());
                    Notification::make()->title('تم إكمال طلب الوصول')->success()->send();
                    $this->refreshFormData(['status', 'user_visible_response']);
                }),
            Action::make('apply_correction')
                ->label('تنفيذ التصحيح')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => auth()->user()?->can('privacy_requests.correction.execute')
                    && $this->getRecord()->request_type === PrivacyRequestType::DataCorrection
                    && in_array($this->getRecord()->status, [PrivacyRequestStatus::Approved, PrivacyRequestStatus::PartiallyApproved], true))
                ->action(function (): void {
                    app(PrivacyRequestService::class)->applyCorrection($this->getRecord(), auth()->user());
                    Notification::make()->title('تم تنفيذ التصحيح')->success()->send();
                    $this->refreshFormData(['status']);
                }),
            Action::make('create_deletion_plan')
                ->label('إنشاء خطة الحذف')
                ->visible(fn (): bool => auth()->user()?->can('privacy_requests.review')
                    && $this->getRecord()->request_type === PrivacyRequestType::AccountDeletion
                    && $this->getRecord()->status === PrivacyRequestStatus::Approved
                    && $this->getRecord()->deletionPlan === null)
                ->action(function (): void {
                    app(DataDeletionPlanService::class)->createDraft($this->getRecord(), auth()->user());
                    Notification::make()->title('تم إنشاء خطة الحذف')->success()->send();
                }),
            Action::make('approve_deletion_plan')
                ->label('اعتماد خطة الحذف')
                ->visible(fn (): bool => auth()->user()?->can('privacy_requests.approve')
                    && $this->getRecord()->request_type === PrivacyRequestType::AccountDeletion
                    && $this->getRecord()->deletionPlan !== null
                    && $this->getRecord()->deletionPlan->status->value === 'ready_for_review')
                ->action(function (): void {
                    $plan = $this->getRecord()->deletionPlan;
                    if ($plan instanceof DataDeletionPlan) {
                        app(DataDeletionPlanService::class)->approve($plan, auth()->user());
                        Notification::make()->title('تم اعتماد خطة الحذف')->success()->send();
                    }
                }),
            Action::make('execute_deletion')
                ->label('تنفيذ الحذف المعتمد')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => auth()->user()?->can('privacy_requests.execute')
                    && $this->getRecord()->request_type === PrivacyRequestType::AccountDeletion
                    && $this->getRecord()->status === PrivacyRequestStatus::Approved
                    && $this->getRecord()->deletionPlan?->status->value === 'approved')
                ->schema([
                    TextInput::make('password')
                        ->label('كلمة المرور')
                        ->password()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $actor = auth()->user();
                    if (! Hash::check((string) $data['password'], (string) $actor->password)) {
                        throw ValidationException::withMessages(['password' => 'كلمة المرور غير صحيحة.']);
                    }

                    SensitiveAccessVerification::markVerified(request());

                    $plan = $this->getRecord()->deletionPlan;
                    if ($plan === null) {
                        throw ValidationException::withMessages(['password' => 'خطة الحذف غير موجودة.']);
                    }

                    app(PersonalDataDeletionService::class)->executeApprovedPlan(
                        $this->getRecord(),
                        $plan,
                        $actor,
                        request(),
                    );

                    Notification::make()->title('اكتمل تنفيذ التعمية')->success()->send();
                }),
        ];
    }
}
