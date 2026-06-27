<?php

namespace App\Filament\Support;

use App\Enums\RegistrationStatus;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Services\PathAttendanceService;
use App\Services\ProgramAttendanceService;
use App\Support\RegistrationEligibilitySupport;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RegistrationFilamentTableSupport
{
    public static function beneficiaryNameColumn(): TextColumn
    {
        return TextColumn::make('user.name')
            ->label('اسم المستفيد')
            ->searchable()
            ->sortable()
            ->wrap()
            ->url(fn (Model $record): ?string => UserFilamentTableSupport::recordUrlFromUserRelation($record));
    }

    public static function configureBeneficiaryRowNavigation(Table $table): Table
    {
        return UserFilamentTableSupport::configureBeneficiaryRowNavigation($table);
    }

    public static function acceptanceStatusColumn(): BadgeColumn
    {
        return BadgeColumn::make('status')
            ->label('حالة القبول')
            ->formatStateUsing(function ($state): string {
                if ($state instanceof RegistrationStatus) {
                    return $state->label();
                }

                return RegistrationStatus::tryFrom((string) $state)?->label() ?? '—';
            })
            ->colors([
                'warning' => RegistrationStatus::Pending->value,
                'success' => RegistrationStatus::Approved->value,
                'danger' => RegistrationStatus::Rejected->value,
                'gray' => RegistrationStatus::Cancelled->value,
                'info' => RegistrationStatus::Completed->value,
            ])
            ->sortable();
    }

    public static function certificateEligibilityColumn(): TextColumn
    {
        return TextColumn::make('certificate_eligibility')
            ->label('أهلية الشهادة')
            ->badge()
            ->getStateUsing(function (ProgramRegistration|PathRegistration $record): string {
                if (in_array($record->status, [
                    RegistrationStatus::Pending,
                    RegistrationStatus::Rejected,
                    RegistrationStatus::Cancelled,
                ], true)) {
                    return '—';
                }

                return RegistrationEligibilitySupport::eligibilityLabel(
                    $record->effectiveAttendancePercentage(),
                    $record->score !== null ? (float) $record->score : null,
                );
            })
            ->color(function (ProgramRegistration|PathRegistration $record): string {
                if (in_array($record->status, [
                    RegistrationStatus::Pending,
                    RegistrationStatus::Rejected,
                    RegistrationStatus::Cancelled,
                ], true)) {
                    return 'gray';
                }

                return RegistrationEligibilitySupport::eligibilityColor(
                    $record->effectiveAttendancePercentage(),
                    $record->score !== null ? (float) $record->score : null,
                );
            });
    }

    public static function attendancePercentageColumn(): TextColumn
    {
        return TextColumn::make('attendance_percentage')
            ->label('نسبة الحضور')
            ->suffix('%')
            ->getStateUsing(fn (ProgramRegistration|PathRegistration $record): string => self::formatPercentage(
                $record->effectiveAttendancePercentage(),
            ))
            ->sortable();
    }

    public static function scoreColumn(): TextColumn
    {
        return TextColumn::make('score')
            ->label('الدرجة')
            ->formatStateUsing(fn ($state): string => $state !== null && $state !== '' ? (string) $state : '—')
            ->sortable();
    }

    public static function formatPercentage(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format($value, 1);
    }

    public static function programAttendanceSummary(ProgramRegistration $record): string
    {
        $record->loadMissing('trainingProgram');
        $expected = app(ProgramAttendanceService::class)->countExpectedTrainingDays($record->trainingProgram);
        $present = $record->attendanceRecords()
            ->where('status', \App\Enums\AttendanceStatus::Present->value)
            ->count();

        if ($expected === 0) {
            return 'لم تُحدَّد أيام البرنامج بعد';
        }

        return "حضور {$present} من {$expected} يوم";
    }

    public static function pathAttendanceSummary(PathRegistration $record): string
    {
        $record->loadMissing('learningPath.programs');
        $expected = app(PathAttendanceService::class)->countExpectedTrainingDays($record->learningPath);
        $present = $record->attendanceRecords()
            ->where('status', \App\Enums\AttendanceStatus::Present->value)
            ->count();

        if ($expected === 0) {
            return 'لم تُحدَّد أيام المسار بعد';
        }

        return "حضور {$present} من {$expected} يوم";
    }
}
