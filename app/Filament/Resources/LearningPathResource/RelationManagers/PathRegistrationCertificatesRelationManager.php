<?php

namespace App\Filament\Resources\LearningPathResource\RelationManagers;

use App\Enums\RegistrationStatus;
use App\Filament\Support\RegistrationFilamentTableSupport;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Services\CertificateService;
use App\Support\RegistrationEligibilitySupport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PathRegistrationCertificatesRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'الشهادات';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($ownerRecord instanceof LearningPath) {
            return $user->can('viewOperational', $ownerRecord);
        }

        return parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return RegistrationFilamentTableSupport::configureBeneficiaryRowNavigation($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ]))
            ->columns([
                RegistrationFilamentTableSupport::beneficiaryNameColumn(),
                RegistrationFilamentTableSupport::attendancePercentageColumn(),
                RegistrationFilamentTableSupport::scoreColumn(),

                TextColumn::make('eligibility_average')
                    ->label('المتوسط')
                    ->suffix('%')
                    ->getStateUsing(fn (PathRegistration $record): string => RegistrationFilamentTableSupport::formatPercentage(
                        RegistrationEligibilitySupport::averageScore(
                            $record->effectiveAttendancePercentage(),
                            $record->score !== null ? (float) $record->score : null,
                        ),
                    )),

                RegistrationFilamentTableSupport::certificateEligibilityColumn(),

                TextColumn::make('certificate_status')
                    ->label('حالة الشهادة')
                    ->badge()
                    ->getStateUsing(fn (PathRegistration $record): string => $record->certificateForEntity() !== null ? 'صادرة' : 'لم تُصدر')
                    ->color(fn (PathRegistration $record): string => $record->certificateForEntity() !== null ? 'success' : 'gray'),
            ])
            ->headerActions([
                Action::make('issueAllEligible')
                    ->label('إصدار الشهادات للمؤهلين')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('إصدار الشهادات للمؤهلين')
                    ->modalDescription('سيتم إصدار شهادة لكل مستفيد يحقق متوسط حضور ودرجة ≥ 75%.')
                    ->modalSubmitActionLabel('نعم، إصدار')
                    ->authorize(fn (): bool => auth()->user()?->can('viewOperational', $this->getOwnerRecord()) ?? false)
                    ->action(function (): void {
                        /** @var LearningPath $path */
                        $path = $this->getOwnerRecord();
                        $count = app(CertificateService::class)->issueEligiblePathRegistrations($path, auth()->user());

                        if ($count > 0) {
                            Notification::make()->title("تم إصدار {$count} شهادة")->success()->send();
                        } else {
                            Notification::make()->title('لا يوجد مستفيدون مؤهلون')->warning()->send();
                        }
                    }),

                Action::make('emailAllEligible')
                    ->label('إرسال إيميلات الشهادات للمؤهلين')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('إرسال إيميلات الشهادات')
                    ->modalDescription('سيُصدر الشهادة تلقائياً إن لم تكن موجودة، ثم يُرسل بريد للمؤهلين.')
                    ->modalSubmitActionLabel('نعم، إرسال')
                    ->authorize(fn (): bool => auth()->user()?->can('viewOperational', $this->getOwnerRecord()) ?? false)
                    ->action(function (): void {
                        /** @var LearningPath $path */
                        $path = $this->getOwnerRecord();
                        $count = app(CertificateService::class)->emailEligiblePathCertificates($path, auth()->user());

                        if ($count > 0) {
                            Notification::make()->title("تم إرسال {$count} بريداً")->success()->send();
                        } else {
                            Notification::make()->title('لا يوجد مستفيدون مؤهلون')->warning()->send();
                        }
                    }),
            ])
            ->actions([
                Action::make('issueCertificate')
                    ->label('إصدار الشهادة')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PathRegistration $record): bool => $record->certificateForEntity() === null && $record->isEligibleForCertificate())
                    ->authorize('update')
                    ->action(function (PathRegistration $record): void {
                        $certificate = app(CertificateService::class)->issueForPathRegistration($record, auth()->user());

                        if ($certificate === null) {
                            Notification::make()
                                ->title('المستفيد غير مؤهل')
                                ->body('يجب إدخال الحضور والدرجة بمتوسط لا يقل عن 75%.')
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()->title('تم إصدار الشهادة')->success()->send();
                    }),

                Action::make('verifyCertificate')
                    ->label('التحقق من الشهادة')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->url(fn (PathRegistration $record): string => route(
                        'certificates.verify',
                        $record->certificateForEntity()?->verification_code ?? '',
                    ))
                    ->openUrlInNewTab()
                    ->visible(fn (PathRegistration $record): bool => $record->certificateForEntity() !== null),

                Action::make('downloadCertificate')
                    ->label('تحميل الشهادة')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->url(fn (PathRegistration $record): string => $record->certificateForEntity()?->downloadUrl() ?? '#')
                    ->openUrlInNewTab()
                    ->visible(fn (PathRegistration $record): bool => $record->certificateForEntity()?->downloadUrl() !== null),
            ])
            ->defaultSort('user.name');
    }
}
