<?php

namespace App\Filament\Resources\VolunteerOpportunityResource\RelationManagers;

use App\Enums\RegistrationStatus;
use App\Exceptions\OpportunityCapacityExceededException;
use App\Models\Certificate;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
use App\Services\CertificateService;
use App\Services\VolunteerRegistrationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'التسجيلات';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->relationship('user', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->label('المتطوع'),

            Select::make('status')
                ->label('الحالة')
                ->options(RegistrationStatus::class)
                ->required(),

            Textarea::make('rejected_reason')
                ->rows(3)
                ->label('سبب الرفض'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable()
                    ->sortable()
                    ->label('المتطوع'),

                TextColumn::make('user.email')
                    ->searchable()
                    ->label('البريد الإلكتروني')
                    ->toggleable(),

                BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'warning' => RegistrationStatus::Pending->value,
                        'success' => RegistrationStatus::Approved->value,
                        'danger' => RegistrationStatus::Rejected->value,
                        'gray' => RegistrationStatus::Cancelled->value,
                        'info' => RegistrationStatus::Completed->value,
                    ]),

                TextColumn::make('approvedBy.name')
                    ->label('اعتمد بواسطة')
                    ->toggleable(),

                TextColumn::make('approved_at')
                    ->label('تاريخ الموافقة')
                    ->dateTime()
                    ->toggleable(),

                TextColumn::make('hours_progress')
                    ->label('تقدم الساعات')
                    ->getStateUsing(fn (VolunteerRegistration $record): string => number_format($record->getApprovedHours(), 1).' / '.
                        number_format((float) optional($record->opportunity)->hours_expected, 1).' ساعة'
                    )
                    ->toggleable(),

                TextColumn::make('has_certificate')
                    ->label('شهادة التطوع')
                    ->badge()
                    ->getStateUsing(function (VolunteerRegistration $record): string {
                        return Certificate::query()
                            ->where('user_id', $record->user_id)
                            ->where('certificateable_type', VolunteerOpportunity::class)
                            ->where('certificateable_id', $record->opportunity_id)
                            ->exists() ? 'صدرت ✓' : '—';
                    })
                    ->color(fn (string $state): string => str_contains($state, 'صدرت') ? 'success' : 'gray'),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(RegistrationStatus::class),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('approve')
                    ->label('قبول الطلب')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد قبول التسجيل')
                    ->modalSubmitActionLabel('نعم، قبول')
                    ->visible(fn (VolunteerRegistration $record): bool => $record->status === RegistrationStatus::Pending)
                    ->action(function (VolunteerRegistration $record): void {
                        try {
                            app(VolunteerRegistrationService::class)->approve($record, auth()->user());
                            Notification::make()->title('تمت الموافقة على التسجيل')->success()->send();
                        } catch (OpportunityCapacityExceededException) {
                            Notification::make()->title('الفرصة بلغت طاقتها القصوى')->danger()->send();
                        }
                    }),

                Action::make('reject')
                    ->label('رفض الطلب')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('رفض طلب التطوع')
                    ->modalSubmitActionLabel('نعم، رفض')
                    ->form([
                        Textarea::make('rejected_reason')
                            ->label('سبب الرفض (اختياري)')
                            ->placeholder('اكتب سبب الرفض لإشعار المستفيد...')
                            ->rows(3),
                    ])
                    ->visible(fn (VolunteerRegistration $record): bool => $record->status === RegistrationStatus::Pending)
                    ->action(function (VolunteerRegistration $record, array $data): void {
                        app(VolunteerRegistrationService::class)->reject(
                            $record,
                            $data['rejected_reason'] ?? null
                        );
                        Notification::make()->title('تم رفض التسجيل')->warning()->send();
                    }),

                Action::make('issueCertificate')
                    ->label('إصدار شهادة تطوع')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('إصدار شهادة تطوع')
                    ->modalDescription('سيتم إصدار شهادة PDF للمتطوع. تأكد من اعتماد جميع الساعات المطلوبة.')
                    ->modalSubmitActionLabel('نعم، إصدار')
                    ->visible(fn (VolunteerRegistration $record): bool => $record->isCompleted())
                    ->action(function (VolunteerRegistration $record): void {
                        $record->loadMissing(['user', 'opportunity']);
                        $existing = Certificate::query()
                            ->where('user_id', $record->user_id)
                            ->where('certificateable_type', VolunteerOpportunity::class)
                            ->where('certificateable_id', $record->opportunity_id)
                            ->first();
                        if ($existing !== null) {
                            Notification::make()
                                ->title('الشهادة موجودة مسبقاً')
                                ->body('رقم الشهادة: '.$existing->certificate_number)
                                ->warning()
                                ->send();

                            return;
                        }
                        app(CertificateService::class)->issue($record->user, $record->opportunity, auth()->user());
                        Notification::make()->title('تم إصدار شهادة التطوع بنجاح')->success()->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
