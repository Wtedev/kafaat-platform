<?php

namespace App\Filament\Resources\TrainingProgramResource\RelationManagers;

use App\Enums\RegistrationStatus;
use App\Exceptions\ProgramCapacityExceededException;
use App\Exceptions\RegistrationNotApprovedException;
use App\Models\Certificate;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Services\CertificateService;
use App\Services\ProgramAttendanceService;
use App\Services\ProgramRegistrationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProgramRegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'المسجلون في البرنامج';

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

            TextInput::make('attendance_percentage')
                ->label('نسبة الحضور %')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%'),

            TextInput::make('score')
                ->label('الدرجة')
                ->numeric()
                ->minValue(0)
                ->maxValue(100),

            Textarea::make('rejected_reason')
                ->rows(3)
                ->columnSpanFull()
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
                    ->label('المستفيد'),

                TextColumn::make('user.email')
                    ->searchable()
                    ->label('البريد الإلكتروني')
                    ->toggleable(),

                BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'warning' => RegistrationStatus::Pending->value,
                        'success' => RegistrationStatus::Approved->value,
                        'danger'  => RegistrationStatus::Rejected->value,
                        'gray'    => RegistrationStatus::Cancelled->value,
                        'info'    => RegistrationStatus::Completed->value,
                    ])
                    ->sortable(),

                TextColumn::make('attendance_percentage')
                    ->label('الحضور')
                    ->suffix('%')
                    ->sortable(),

                TextColumn::make('score')
                    ->label('الدرجة')
                    ->sortable(),

                TextColumn::make('eligibility')
                    ->label('أهلية الشهادة')
                    ->badge()
                    ->getStateUsing(function (ProgramRegistration $record): string {
                        if (in_array($record->status, [
                            RegistrationStatus::Pending,
                            RegistrationStatus::Rejected,
                            RegistrationStatus::Cancelled,
                        ])) {
                            return '—';
                        }
                        if ($record->attendance_percentage === null) {
                            return 'بانتظار البيانات';
                        }
                        $attOk   = (float) $record->attendance_percentage >= 80;
                        $scoreOk = $record->score === null || (float) $record->score >= 60;

                        return ($attOk && $scoreOk) ? 'مؤهل ✓' : 'غير مؤهل بعد';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'مؤهل ✓'              => 'success',
                        'غير مؤهل بعد'       => 'danger',
                        'بانتظار البيانات'    => 'warning',
                        default               => 'gray',
                    }),

                TextColumn::make('has_certificate')
                    ->label('الشهادة')
                    ->badge()
                    ->getStateUsing(function (ProgramRegistration $record): string {
                        return Certificate::query()
                            ->where('user_id', $record->user_id)
                            ->where('certificateable_type', TrainingProgram::class)
                            ->where('certificateable_id', $record->training_program_id)
                            ->exists() ? 'صدرت ✓' : '—';
                    })
                    ->color(fn (string $state): string => str_contains($state, 'صدرت') ? 'success' : 'gray'),

                TextColumn::make('approved_at')
                    ->label('تاريخ القبول')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('تاريخ التسجيل')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(RegistrationStatus::class),
            ])
            ->headerActions([
                Action::make('generateAllSessions')
                    ->label('توليد الجلسات لجميع المسجلين')
                    ->icon('heroicon-o-calendar-days')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('توليد الجلسات التدريبية لجميع المسجلين')
                    ->modalDescription(
                        'سيتم إنشاء سجلات حضور لكل المسجلين المقبولين والمكتملين. '
                        . 'يتطلب تحديد أيام الدراسة وتاريخَي بداية ونهاية البرنامج. '
                        . 'السجلات الموجودة لن تُعدَّل.'
                    )
                    ->modalSubmitActionLabel('نعم، توليد')
                    ->action(function (RelationManager $livewire): void {
                        /** @var TrainingProgram $program */
                        $program = $livewire->getOwnerRecord();
                        $count   = app(ProgramAttendanceService::class)
                            ->generateSessionsForAllRegistrations($program);

                        if ($count === 0) {
                            Notification::make()
                                ->title('لم تُنشأ جلسات جديدة')
                                ->body('تأكد من تحديد أيام الدراسة وتواريخ البرنامج، وأن يكون هناك مسجلون مقبولون.')
                                ->warning()
                                ->send();
                        } else {
                            Notification::make()
                                ->title("تم توليد {$count} جلسة تدريبية لجميع المسجلين")
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Action::make('approve')
                    ->label('قبول الطلب')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد قبول الطلب')
                    ->modalDescription('هل تريد قبول طلب التسجيل في هذا البرنامج؟')
                    ->modalSubmitActionLabel('نعم، قبول')
                    ->visible(fn (ProgramRegistration $record): bool => $record->status === RegistrationStatus::Pending)
                    ->action(function (ProgramRegistration $record): void {
                        try {
                            app(ProgramRegistrationService::class)->approve($record, auth()->user());
                            Notification::make()->title('تمت الموافقة على التسجيل')->success()->send();
                        } catch (ProgramCapacityExceededException) {
                            Notification::make()->title('البرنامج بلغ طاقته القصوى')->danger()->send();
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
                    ->visible(fn (ProgramRegistration $record): bool => $record->status === RegistrationStatus::Pending)
                    ->action(function (ProgramRegistration $record, array $data): void {
                        app(ProgramRegistrationService::class)->reject($record, $data['rejected_reason'] ?? null);
                        Notification::make()->title('تم رفض التسجيل')->warning()->send();
                    }),

                Action::make('updateAttendance')
                    ->label('تحديث الحضور والدرجة')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->visible(fn (ProgramRegistration $record): bool => $record->isApproved())
                    ->fillForm(fn (ProgramRegistration $record): array => [
                        'attendance_percentage' => $record->calculateAttendancePercentage() ?? $record->attendance_percentage,
                        'score'                 => $record->score,
                    ])
                    ->form([
                        TextInput::make('attendance_percentage')
                            ->label('نسبة الحضور %')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText('الحد الأدنى للحصول على شهادة: 80٪'),

                        TextInput::make('score')
                            ->label('الدرجة')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('الحد الأدنى للحصول على شهادة: 60'),
                    ])
                    ->action(function (ProgramRegistration $record, array $data): void {
                        $record->update([
                            'attendance_percentage' => (float) $data['attendance_percentage'],
                            'score'                 => ($data['score'] !== null && $data['score'] !== '')
                                ? (float) $data['score']
                                : $record->score,
                        ]);
                        Notification::make()->title('تم تحديث بيانات الحضور والدرجة')->success()->send();
                    }),

                Action::make('markCompleted')
                    ->label('إنهاء البرنامج')
                    ->icon('heroicon-o-trophy')
                    ->color('info')
                    ->visible(fn (ProgramRegistration $record): bool => $record->isApproved())
                    ->fillForm(fn (ProgramRegistration $record): array => [
                        'attendance_percentage' => $record->calculateAttendancePercentage() ?? $record->attendance_percentage,
                        'score'                 => $record->score,
                    ])
                    ->form([
                        TextInput::make('attendance_percentage')
                            ->label('نسبة الحضور %')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText('الحد الأدنى لإصدار الشهادة: 80٪'),

                        TextInput::make('score')
                            ->label('الدرجة')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('الحد الأدنى لإصدار الشهادة: 60'),
                    ])
                    ->action(function (ProgramRegistration $record, array $data): void {
                        try {
                            app(ProgramRegistrationService::class)->markCompleted(
                                registration:         $record,
                                admin:                auth()->user(),
                                score:                ($data['score'] !== null && $data['score'] !== '')
                                    ? (float) $data['score']
                                    : null,
                                attendancePercentage: (float) $data['attendance_percentage'],
                            );

                            $hasCert = Certificate::query()
                                ->where('user_id', $record->user_id)
                                ->where('certificateable_type', TrainingProgram::class)
                                ->where('certificateable_id', $record->training_program_id)
                                ->exists();

                            if ($hasCert) {
                                Notification::make()->title('تم إنهاء البرنامج وصدرت الشهادة تلقائياً')->success()->send();
                            } else {
                                Notification::make()
                                    ->title('تم إنهاء البرنامج')
                                    ->body('لم تُصدر شهادة — الحضور يجب أن يكون ≥ 80٪ والدرجة ≥ 60')
                                    ->warning()
                                    ->send();
                            }
                        } catch (RegistrationNotApprovedException) {
                            Notification::make()->title('لا يمكن إكمال تسجيل غير مقبول')->danger()->send();
                        }
                    }),

                Action::make('issueCertificate')
                    ->label('إصدار شهادة')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('إصدار شهادة إتمام البرنامج')
                    ->modalDescription('سيتم إصدار شهادة PDF للمستفيد. تأكد من استيفاء شروط الحضور والدرجة.')
                    ->modalSubmitActionLabel('نعم، إصدار')
                    ->visible(fn (ProgramRegistration $record): bool => $record->isCompleted())
                    ->action(function (ProgramRegistration $record): void {
                        if (! $record->isEligibleForCertificate()) {
                            Notification::make()
                                ->title('المستفيد غير مؤهل للشهادة')
                                ->body('يجب أن يكون الحضور ≥ 80٪ والدرجة ≥ 60')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->loadMissing(['user', 'trainingProgram']);
                        $existing = Certificate::query()
                            ->where('user_id', $record->user_id)
                            ->where('certificateable_type', TrainingProgram::class)
                            ->where('certificateable_id', $record->training_program_id)
                            ->first();

                        if ($existing !== null) {
                            Notification::make()
                                ->title('الشهادة موجودة مسبقاً')
                                ->body('رقم الشهادة: ' . $existing->certificate_number)
                                ->warning()
                                ->send();

                            return;
                        }

                        app(CertificateService::class)->issue($record->user, $record->trainingProgram);
                        Notification::make()->title('تم إصدار الشهادة بنجاح')->success()->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
