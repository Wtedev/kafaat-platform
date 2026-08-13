<?php

namespace App\Filament\Resources\ProgramRegistrationResource\RelationManagers;

use App\Models\ProgramAttendance;
use App\Models\ProgramRegistration;
use App\Services\ProgramAttendanceService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AttendanceRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceRecords';

    protected static ?string $title = 'سجل الحضور';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($ownerRecord instanceof ProgramRegistration) {
            $ownerRecord->loadMissing('trainingProgram');

            return $ownerRecord->trainingProgram !== null
                && $user->can('viewOperational', $ownerRecord->trainingProgram);
        }

        return parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Schema $schema): Schema
    {
        /** @var ProgramRegistration $registration */
        $registration = $this->getOwnerRecord();
        $registration->loadMissing('trainingProgram');
        $options = app(ProgramAttendanceService::class)
            ->attendancePrepDateOptions($registration->trainingProgram);

        return $schema->components([
            Select::make('training_date')
                ->label('يوم البرنامج')
                ->options($options)
                ->required()
                ->native(false),
        ]);
    }

    public function table(Table $table): Table
    {
        /** @var ProgramRegistration $registration */
        $registration = $this->getOwnerRecord();
        $registration->loadMissing('trainingProgram');
        $summary = app(ProgramAttendanceService::class)->getSummary($registration);
        $prepOptions = app(ProgramAttendanceService::class)
            ->attendancePrepDateOptions($registration->trainingProgram);

        return $table
            ->description(
                sprintf(
                    'أيام البرنامج: %d | حاضر: %d | لم يحضر: %d',
                    $summary['total'],
                    $summary['present'],
                    $summary['not_present'],
                )
            )
            ->columns([
                TextColumn::make('training_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('day_name')
                    ->label('اليوم')
                    ->getStateUsing(fn (ProgramAttendance $record): string => match ($record->training_date?->dayOfWeek) {
                        0 => 'الأحد',
                        1 => 'الاثنين',
                        2 => 'الثلاثاء',
                        3 => 'الأربعاء',
                        4 => 'الخميس',
                        5 => 'الجمعة',
                        6 => 'السبت',
                        default => '—',
                    }),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->getStateUsing(fn (): string => 'حاضر')
                    ->color('success'),

                TextColumn::make('internal_notes')
                    ->label('ملاحظة داخلية')
                    ->placeholder('—')
                    ->wrap()
                    ->limit(60)
                    ->tooltip('لا يراها المستفيد'),
            ])
            ->headerActions([
                Action::make('addDay')
                    ->label('تسجيل حضور')
                    ->icon('heroicon-o-plus')
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->visible(fn (): bool => $prepOptions !== [])
                    ->form([
                        Select::make('training_date')
                            ->label('يوم البرنامج')
                            ->options($prepOptions)
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (array $data) use ($registration): void {
                        app(ProgramAttendanceService::class)->markPresent(
                            $registration,
                            (string) $data['training_date'],
                            Auth::user(),
                        );

                        Notification::make()->title('تم تسجيل الحضور')->success()->send();
                    }),
            ])
            ->actions([
                DeleteAction::make()
                    ->label('لم يحضر')
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->using(function (Model $record): bool {
                        /** @var ProgramAttendance $record */
                        /** @var ProgramRegistration $registration */
                        $registration = $this->getOwnerRecord();

                        app(ProgramAttendanceService::class)->clearDay(
                            $registration,
                            $record->training_date->toDateString(),
                            Auth::user(),
                        );

                        return true;
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('training_date', 'asc');
    }
}
