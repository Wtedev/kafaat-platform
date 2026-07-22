<?php

namespace App\Filament\Resources\TrainingProgramResource\RelationManagers;

use App\Enums\AttendanceStatus;
use App\Enums\ProgramDeliveryMode;
use App\Enums\RegistrationStatus;
use App\Filament\Concerns\InteractsWithAttendanceLiveSession;
use App\Filament\Support\RegistrationFilamentTableSupport;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Services\ProgramAttendanceService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProgramAttendanceRegistrationsRelationManager extends RelationManager
{
    use InteractsWithAttendanceLiveSession;

    protected static string $relationship = 'registrations';

    protected static ?string $title = 'التحضير';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($ownerRecord instanceof TrainingProgram) {
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
            ->poll(fn (): ?string => $this->isRemoteProgram()
                ? $this->attendanceLiveSessionTablePollInterval()
                : null)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ]))
            ->columns([
                RegistrationFilamentTableSupport::beneficiaryNameColumn(),

                TextColumn::make('attendance_days')
                    ->label('أيام الحضور')
                    ->getStateUsing(fn (ProgramRegistration $record): string => RegistrationFilamentTableSupport::programAttendanceSummary($record)),

                RegistrationFilamentTableSupport::attendancePercentageColumn(),
            ])
            ->headerActions([
                Action::make('openGateScan')
                    ->label('مسح QR للتحضير')
                    ->icon('heroicon-o-qr-code')
                    ->color('success')
                    ->url(fn (): string => route('gate.scan', ['program' => $this->ownerProgram()->slug]))
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => $this->isInPersonProgram())
                    ->authorize(fn (): bool => auth()->user()?->can('viewOperational', $this->getOwnerRecord()) ?? false),

                Action::make('startLiveSession')
                    ->label('فتح جلسة حضور (5 دقائق)')
                    ->icon('heroicon-o-signal')
                    ->color('success')
                    ->visible(fn (): bool => $this->isRemoteProgram() && $this->activeAttendanceSession() === null)
                    ->authorize(fn (): bool => auth()->user()?->can('viewOperational', $this->getOwnerRecord()) ?? false)
                    ->action(fn (): mixed => $this->startAttendanceLiveSession()),

                $this->makeAttendanceLiveSessionCountdownAction()
                    ->visible(fn (): bool => $this->isRemoteProgram() && ($this->activeAttendanceSession()?->isActive() ?? false)),

                Action::make('generateAllSessions')
                    ->label('توليد جلسات البرنامج')
                    ->icon('heroicon-o-calendar-days')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('توليد جلسات الحضور')
                    ->modalDescription('سيتم إنشاء سجلات حضور لكل أيام البرنامج المتوقعة لجميع المسجلين المقبولين.')
                    ->modalSubmitActionLabel('نعم، توليد')
                    ->visible(fn (): bool => $this->isRemoteProgram())
                    ->authorize(fn (): bool => auth()->user()?->can('viewOperational', $this->getOwnerRecord()) ?? false)
                    ->action(function (): void {
                        $program = $this->ownerProgram();
                        $count = app(ProgramAttendanceService::class)->generateSessionsForAllRegistrations($program);

                        if ($count > 0) {
                            Notification::make()
                                ->title("تم توليد {$count} جلسة")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('لم تُنشأ جلسات جديدة')
                                ->warning()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Action::make('manualAttendance')
                    ->label('تحضير يدوي')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->authorize('update')
                    ->form([
                        DatePicker::make('training_date')
                            ->label('تاريخ اليوم')
                            ->required()
                            ->default(today()),

                        Select::make('status')
                            ->label('الحالة')
                            ->options(AttendanceStatus::class)
                            ->required()
                            ->default(AttendanceStatus::Present->value),

                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->rows(2),
                    ])
                    ->action(function (ProgramRegistration $record, array $data): void {
                        app(ProgramAttendanceService::class)->markManualDay(
                            $record,
                            (string) $data['training_date'],
                            AttendanceStatus::from((string) $data['status']),
                            $data['notes'] ?? null,
                        );

                        Notification::make()->title('تم تحديث الحضور')->success()->send();
                    }),
            ])
            ->defaultSort('user.name');
    }

    protected function ownerProgram(): TrainingProgram
    {
        $program = $this->getOwnerRecord();
        assert($program instanceof TrainingProgram);

        return $program;
    }

    protected function isInPersonProgram(): bool
    {
        return $this->ownerProgram()->delivery_mode?->hasPhysicalComponent() ?? false;
    }

    protected function isRemoteProgram(): bool
    {
        return $this->ownerProgram()->delivery_mode === ProgramDeliveryMode::Remote;
    }
}
