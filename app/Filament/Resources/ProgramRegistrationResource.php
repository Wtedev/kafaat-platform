<?php

namespace App\Filament\Resources;

use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\ProgramCapacityExceededException;
use App\Exceptions\RegistrationNotApprovedException;
use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\ProgramRegistrationResource\Pages;
use App\Filament\Resources\ProgramRegistrationResource\RelationManagers\AttendanceRelationManager;
use App\Models\Certificate;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Services\CertificateService;
use App\Services\ProgramRegistrationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProgramRegistrationResource extends Resource
{
    use RegistersNavigationByPermission;

    protected static ?string $model = ProgramRegistration::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'التدريب';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'تسجيلات البرامج';

    protected static ?string $modelLabel = 'تسجيل برنامج';

    protected static ?string $pluralModelLabel = 'تسجيلات البرامج';

    protected static function requiredNavigationPermissions(): array
    {
        return ['roles.view'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('training_program_id')
                    ->relationship(
                        'trainingProgram',
                        'title',
                        modifyQueryUsing: fn (Builder $q) => $q->forFilamentAssignmentAccess(auth()->user()),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('البرنامج التدريبي'),

                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('المستخدم'),

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
                    ->minValue(0),

                Textarea::make('rejected_reason')
                    ->rows(3)
                    ->columnSpanFull()
                    ->label('سبب الرفض'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')
                    ->label('البريد الإلكتروني')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('trainingProgram.title')
                    ->label('البرنامج')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('trainingProgram.status')
                    ->label('حالة البرنامج')
                    ->colors([
                        'gray' => ProgramStatus::Draft->value,
                        'success' => ProgramStatus::Published->value,
                        'warning' => ProgramStatus::Archived->value,
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),

                BadgeColumn::make('status')
                    ->label('حالة التسجيل')
                    ->colors([
                        'warning' => RegistrationStatus::Pending->value,
                        'success' => RegistrationStatus::Approved->value,
                        'danger' => RegistrationStatus::Rejected->value,
                        'gray' => RegistrationStatus::Cancelled->value,
                        'info' => RegistrationStatus::Completed->value,
                    ])
                    ->sortable(),

                TextColumn::make('attendance_percentage')
                    ->label('نسبة الحضور')
                    ->suffix('%')
                    ->sortable()
                    ->toggleable()
                    ->description('محسوبة من الحضور اليومي'),

                TextColumn::make('score')
                    ->label('الدرجة')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('has_certificate')
                    ->label('شهادة البرنامج')
                    ->badge()
                    ->getStateUsing(function (ProgramRegistration $record): string {
                        return Certificate::query()
                            ->where('user_id', $record->user_id)
                            ->where('certificateable_type', TrainingProgram::class)
                            ->where('certificateable_id', $record->training_program_id)
                            ->exists() ? 'صدرت ✓' : '—';
                    })
                    ->color(fn (string $state): string => str_contains($state, 'صدرت') ? 'success' : 'gray')
                    ->toggleable(),

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
                        $attOk = (float) $record->attendance_percentage >= 80;
                        $scoreOk = $record->score === null || (float) $record->score >= 60;

                        return ($attOk && $scoreOk) ? 'مؤهل ✓' : 'غير مؤهل بعد';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'مؤهل ✓' => 'success',
                        'غير مؤهل بعد' => 'danger',
                        'بانتظار البيانات' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),

                TextColumn::make('approvedBy.name')
                    ->label('اعتمد بواسطة')
                    ->toggleable(),

                TextColumn::make('approved_at')
                    ->label('تاريخ الموافقة')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

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

                SelectFilter::make('training_program_id')
                    ->relationship(
                        'trainingProgram',
                        'title',
                        modifyQueryUsing: fn (Builder $q) => $q->forFilamentAssignmentAccess(auth()->user()),
                    )
                    ->label('البرنامج')
                    ->searchable(),
            ])
            ->actions([
                ViewAction::make(),

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
                            Notification::make()
                                ->title('تمت الموافقة على التسجيل')
                                ->success()
                                ->send();
                        } catch (ProgramCapacityExceededException) {
                            Notification::make()
                                ->title('البرنامج بلغ طاقته القصوى')
                                ->danger()
                                ->send();
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
                        app(ProgramRegistrationService::class)->reject(
                            $record,
                            $data['rejected_reason'] ?? null
                        );
                        Notification::make()
                            ->title('تم رفض التسجيل')
                            ->warning()
                            ->send();
                    }),

                Action::make('updateAttendance')
                    ->label('تحديث الحضور والدرجة')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->visible(fn (ProgramRegistration $record): bool => $record->isApproved())
                    ->fillForm(fn (ProgramRegistration $record): array => [
                        'attendance_percentage' => $record->attendance_percentage,
                        'score' => $record->score,
                    ])
                    ->form([
                        TextInput::make('attendance_percentage')
                            ->label('نسبة الحضور %')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%'),
                        TextInput::make('score')
                            ->label('الدرجة')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100),
                    ])
                    ->action(function (ProgramRegistration $record, array $data): void {
                        $record->update([
                            'attendance_percentage' => (float) $data['attendance_percentage'],
                            'score' => ($data['score'] !== null && $data['score'] !== '')
                                ? (float) $data['score']
                                : $record->score,
                        ]);
                        Notification::make()
                            ->title('تم تحديث بيانات الحضور والدرجة')
                            ->success()
                            ->send();
                    }),

                Action::make('markCompleted')
                    ->label('إنهاء البرنامج')
                    ->icon('heroicon-o-trophy')
                    ->color('info')
                    ->visible(fn (ProgramRegistration $record): bool => $record->isApproved())
                    ->fillForm(fn (ProgramRegistration $record): array => [
                        // Pre-fill with calculated value from daily records if available
                        'attendance_percentage' => $record->calculateAttendancePercentage() ?? $record->attendance_percentage,
                        'score' => $record->score,
                    ])
                    ->form([
                        TextInput::make('attendance_percentage')
                            ->label('نسبة الحضور %')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText('إذا وجدت جلسات يومية، تُحسب تلقائياً. يمكن تجاوز القيمة يدوياً هنا.'),
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
                                registration: $record,
                                admin: auth()->user(),
                                score: ($data['score'] !== null && $data['score'] !== '')
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
                                Notification::make()
                                    ->title('تم تحديد التسجيل كمكتمل وصدرت الشهادة')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('تم تحديد التسجيل كمكتمل')
                                    ->body('لم تُصدر شهادة — يجب أن يكون الحضور ≥ 80٪ والدرجة ≥ 60')
                                    ->warning()
                                    ->send();
                            }
                        } catch (RegistrationNotApprovedException) {
                            Notification::make()
                                ->title('لا يمكن إكمال تسجيل غير مقبول')
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('issueCertificate')
                    ->label('إصدار شهادة')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->visible(fn (ProgramRegistration $record): bool => $record->isCompleted())
                    ->requiresConfirmation()
                    ->action(function (ProgramRegistration $record): void {
                        if (! $record->isEligibleForCertificate()) {
                            Notification::make()
                                ->title('غير مؤهل للحصول على شهادة')
                                ->body('يجب أن يكون الحضور ≥ 80٪ والدرجة ≥ 60')
                                ->danger()
                                ->send();

                            return;
                        }
                        $record->loadMissing(['user', 'trainingProgram']);
                        app(CertificateService::class)->issue($record->user, $record->trainingProgram, auth()->user());
                        Notification::make()
                            ->title('تم إصدار الشهادة بنجاح')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->forFilamentAssignmentAccess(auth()->user()))
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            AttendanceRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProgramRegistrations::route('/'),
            'view' => Pages\ViewProgramRegistration::route('/{record}'),
        ];
    }
}
