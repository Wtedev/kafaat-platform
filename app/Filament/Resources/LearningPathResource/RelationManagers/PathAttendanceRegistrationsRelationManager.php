<?php

namespace App\Filament\Resources\LearningPathResource\RelationManagers;

use App\Enums\AttendanceStatus;
use App\Enums\RegistrationStatus;
use App\Filament\Concerns\InteractsWithAttendanceLiveSession;
use App\Filament\Support\RegistrationFilamentTableSupport;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Services\PathAttendanceService;
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

class PathAttendanceRegistrationsRelationManager extends RelationManager
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
            ->poll(fn (): ?string => $this->attendanceLiveSessionTablePollInterval())
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ]))
            ->columns([
                RegistrationFilamentTableSupport::beneficiaryNameColumn(),

                TextColumn::make('attendance_days')
                    ->label('أيام الحضور')
                    ->getStateUsing(fn (PathRegistration $record): string => RegistrationFilamentTableSupport::pathAttendanceSummary($record)),

                RegistrationFilamentTableSupport::attendancePercentageColumn(),
            ])
            ->headerActions([
                Action::make('startLiveSession')
                    ->label('فتح جلسة حضور (5 دقائق)')
                    ->icon('heroicon-o-signal')
                    ->color('success')
                    ->visible(fn (): bool => $this->activeAttendanceSession() === null)
                    ->authorize(fn (): bool => auth()->user()?->can('viewOperational', $this->getOwnerRecord()) ?? false)
                    ->action(fn (): mixed => $this->startAttendanceLiveSession()),

                $this->makeAttendanceLiveSessionCountdownAction(),

                Action::make('generateAllSessions')
                    ->label('توليد جلسات المسار')
                    ->icon('heroicon-o-calendar-days')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('توليد جلسات الحضور')
                    ->modalDescription('سيتم إنشاء سجلات حضور لجميع أيام برامج المسار لكل المسجلين المقبولين.')
                    ->modalSubmitActionLabel('نعم، توليد')
                    ->authorize(fn (): bool => auth()->user()?->can('viewOperational', $this->getOwnerRecord()) ?? false)
                    ->action(function (): void {
                        /** @var LearningPath $path */
                        $path = $this->getOwnerRecord();
                        $count = app(PathAttendanceService::class)->generateSessionsForAllRegistrations($path);

                        if ($count > 0) {
                            Notification::make()->title("تم توليد {$count} جلسة")->success()->send();
                        } else {
                            Notification::make()->title('لم تُنشأ جلسات جديدة')->warning()->send();
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
                        DatePicker::make('attendance_date')
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
                    ->action(function (PathRegistration $record, array $data): void {
                        app(PathAttendanceService::class)->markManualDay(
                            $record,
                            (string) $data['attendance_date'],
                            AttendanceStatus::from((string) $data['status']),
                            $data['notes'] ?? null,
                        );

                        Notification::make()->title('تم تحديث الحضور')->success()->send();
                    }),
            ])
            ->defaultSort('user.name');
    }
}
