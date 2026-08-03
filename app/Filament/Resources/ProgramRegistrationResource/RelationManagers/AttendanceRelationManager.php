<?php

namespace App\Filament\Resources\ProgramRegistrationResource\RelationManagers;

use App\Enums\AttendanceStatus;
use App\Models\ProgramAttendance;
use App\Models\ProgramRegistration;
use App\Services\ProgramAttendanceService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AttendanceRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceRecords';

    protected static ?string $title = 'سجل الحضور اليومي';

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
                ->label('يوم التحضير')
                ->options($options)
                ->required()
                ->native(false)
                ->disabled(fn (string $operation): bool => $operation === 'edit'),

            Select::make('status')
                ->label('الحالة')
                ->options(AttendanceStatus::options())
                ->required()
                ->default(AttendanceStatus::Present->value),

            Textarea::make('notes')
                ->label('ملاحظات')
                ->rows(2)
                ->columnSpanFull(),
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
                    'أيام التحضير: %d | حاضر: %d | متأخر: %d | غائب: %d | بعذر: %d | غير محدد: %d',
                    $summary['total'],
                    $summary['present'],
                    $summary['late'],
                    $summary['absent'],
                    $summary['excused'],
                    $summary['unspecified'],
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

                BadgeColumn::make('status')
                    ->label('الحالة')
                    ->formatStateUsing(fn ($state): string => $state instanceof AttendanceStatus
                        ? $state->label()
                        : (AttendanceStatus::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->colors([
                        'success' => AttendanceStatus::Present->value,
                        'warning' => AttendanceStatus::Late->value,
                        'danger' => AttendanceStatus::Absent->value,
                        'info' => AttendanceStatus::Excused->value,
                    ]),

                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(60)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(AttendanceStatus::options()),
            ])
            ->headerActions([
                Action::make('addDay')
                    ->label('تسجيل يوم')
                    ->icon('heroicon-o-plus')
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->visible(fn (): bool => $prepOptions !== [])
                    ->form([
                        Select::make('training_date')
                            ->label('يوم التحضير')
                            ->options($prepOptions)
                            ->required()
                            ->native(false),
                        Select::make('status')
                            ->label('الحالة')
                            ->options(AttendanceStatus::options())
                            ->required()
                            ->default(AttendanceStatus::Present->value),
                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->rows(2),
                    ])
                    ->action(function (array $data) use ($registration): void {
                        app(ProgramAttendanceService::class)->markManualDay(
                            $registration,
                            (string) $data['training_date'],
                            AttendanceStatus::from((string) $data['status']),
                            $data['notes'] ?? null,
                            Auth::user(),
                        );

                        Notification::make()->title('تم تحديث الحضور')->success()->send();
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->label('تعديل الحالة')
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->using(function (Model $record, array $data): Model {
                        /** @var ProgramAttendance $record */
                        /** @var ProgramRegistration $registration */
                        $registration = $this->getOwnerRecord();

                        try {
                            app(ProgramAttendanceService::class)->markManualDay(
                                $registration,
                                $record->training_date->toDateString(),
                                AttendanceStatus::from((string) $data['status']),
                                $data['notes'] ?? null,
                                Auth::user(),
                            );
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();
                        }

                        return $record->refresh();
                    }),
                Action::make('clearDay')
                    ->label('حذف (غير محدد)')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->action(function (ProgramAttendance $record) use ($registration): void {
                        app(ProgramAttendanceService::class)->clearDay(
                            $registration,
                            $record->training_date->toDateString(),
                            Auth::user(),
                        );

                        Notification::make()->title('تم إعادة اليوم إلى غير محدد')->success()->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('clearDays')
                        ->label('حذف (غير محدد)')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                        ->action(function (EloquentCollection $records) use ($registration): void {
                            $service = app(ProgramAttendanceService::class);

                            foreach ($records as $record) {
                                if (! $record instanceof ProgramAttendance) {
                                    continue;
                                }

                                $service->clearDay(
                                    $registration,
                                    $record->training_date->toDateString(),
                                    Auth::user(),
                                );
                            }

                            Notification::make()->title('تم إعادة الأيام المحددة إلى غير محدد')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('training_date', 'asc');
    }
}
