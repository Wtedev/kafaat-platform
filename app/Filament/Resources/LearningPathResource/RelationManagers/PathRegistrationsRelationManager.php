<?php

namespace App\Filament\Resources\LearningPathResource\RelationManagers;

use App\Enums\RegistrationStatus;
use App\Exceptions\PathCapacityExceededException;
use App\Models\Certificate;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Services\CertificateService;
use App\Services\PathRegistrationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PathRegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'المسجلون في المسار';

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
        return $schema->components([
            Select::make('user_id')
                ->relationship('user', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->label('المستفيد'),

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
            ->heading('')
            ->columns([
                TextColumn::make('user.name')
                    ->searchable()
                    ->sortable()
                    ->label('المستفيد'),

                TextColumn::make('user.email')
                    ->searchable()
                    ->label('البريد الإلكتروني')
                    ->toggleable(isToggledHiddenByDefault: true),

                BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'warning' => RegistrationStatus::Pending->value,
                        'success' => RegistrationStatus::Approved->value,
                        'danger' => RegistrationStatus::Rejected->value,
                        'gray' => RegistrationStatus::Cancelled->value,
                        'info' => RegistrationStatus::Completed->value,
                    ])
                    ->sortable(),
            ])
            ->actions([
                Action::make('approve')
                    ->label('قبول الطلب')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد قبول الطلب')
                    ->modalDescription('هل تريد قبول طلب التسجيل في هذا المسار؟')
                    ->modalSubmitActionLabel('نعم، قبول')
                    ->visible(fn (PathRegistration $record): bool => $record->status === RegistrationStatus::Pending)
                    ->authorize('approve')
                    ->action(function (PathRegistration $record): void {
                        try {
                            app(PathRegistrationService::class)->approve($record, auth()->user());
                            Notification::make()->title('تمت الموافقة على التسجيل')->success()->send();
                        } catch (PathCapacityExceededException) {
                            Notification::make()->title('المسار بلغ طاقته القصوى')->danger()->send();
                        }
                    }),

                Action::make('reject')
                    ->label('رفض الطلب')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('رفض طلب التسجيل')
                    ->modalSubmitActionLabel('نعم، رفض')
                    ->form([
                        Textarea::make('rejected_reason')
                            ->label('سبب الرفض (اختياري)')
                            ->placeholder('اكتب سبب الرفض لإشعار المستفيد...')
                            ->rows(3),
                    ])
                    ->visible(fn (PathRegistration $record): bool => $record->status === RegistrationStatus::Pending)
                    ->authorize('reject')
                    ->action(function (PathRegistration $record, array $data): void {
                        app(PathRegistrationService::class)->reject($record, $data['rejected_reason'] ?? null);
                        Notification::make()->title('تم رفض التسجيل')->warning()->send();
                    }),

                Action::make('complete')
                    ->label('إنهاء المسار')
                    ->icon('heroicon-o-trophy')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('تسجيل إكمال المسار')
                    ->modalDescription('سيتم تغيير حالة التسجيل إلى مكتمل. هل أنت متأكد؟')
                    ->modalSubmitActionLabel('نعم، إنهاء')
                    ->visible(fn (PathRegistration $record): bool => $record->status === RegistrationStatus::Approved)
                    ->authorize('update')
                    ->action(function (PathRegistration $record): void {
                        app(PathRegistrationService::class)->complete($record);
                        Notification::make()->title('تم تسجيل إكمال المسار')->success()->send();
                    }),

                Action::make('issueCertificate')
                    ->label('إصدار شهادة')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('إصدار شهادة إتمام المسار')
                    ->modalDescription('سيتم إصدار شهادة PDF للمستفيد.')
                    ->modalSubmitActionLabel('نعم، إصدار')
                    ->visible(fn (PathRegistration $record): bool => $record->isCompleted())
                    ->authorize('update')
                    ->action(function (PathRegistration $record): void {
                        $record->loadMissing(['user', 'learningPath']);
                        $existing = Certificate::query()
                            ->where('user_id', $record->user_id)
                            ->where('certificateable_type', LearningPath::class)
                            ->where('certificateable_id', $record->learning_path_id)
                            ->first();
                        if ($existing !== null) {
                            Notification::make()
                                ->title('الشهادة موجودة مسبقاً')
                                ->body('رقم الشهادة: '.$existing->certificate_number)
                                ->warning()
                                ->send();

                            return;
                        }
                        app(CertificateService::class)->issue($record->user, $record->learningPath, auth()->user());
                        Notification::make()->title('تم إصدار الشهادة بنجاح')->success()->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
