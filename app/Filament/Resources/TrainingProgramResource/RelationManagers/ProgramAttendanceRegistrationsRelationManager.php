<?php

namespace App\Filament\Resources\TrainingProgramResource\RelationManagers;

use App\Enums\AttendanceStatus;
use App\Enums\ProgramDeliveryMode;
use App\Enums\RegistrationStatus;
use App\Filament\Concerns\InteractsWithAttendanceLiveSession;
use App\Filament\Support\RegistrationFilamentTableSupport;
use App\Models\ProgramPrepDay;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Services\ProgramAttendanceService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ProgramAttendanceRegistrationsRelationManager extends RelationManager
{
    use InteractsWithAttendanceLiveSession;

    protected static string $relationship = 'registrations';

    protected static ?string $title = 'التحضير';

    /** @var 'daily'|'matrix' */
    public string $attendanceMode = 'daily';

    public ?string $selectedPrepDate = null;

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

    public function updatedSelectedPrepDate(): void
    {
        $service = app(ProgramAttendanceService::class);
        $program = $this->ownerProgram();

        if (
            $this->selectedPrepDate === null
            || $this->selectedPrepDate === ''
            || ! $service->isValidAttendancePrepDate($program, $this->selectedPrepDate)
        ) {
            $this->selectedPrepDate = $service->defaultPrepDate($program);
        }

        $this->resetTable();
    }

    public function setAttendanceMode(string $mode): void
    {
        $this->attendanceMode = in_array($mode, ['daily', 'matrix'], true) ? $mode : 'daily';
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $service = app(ProgramAttendanceService::class);
        $program = $this->ownerProgram();
        $prepOptions = $service->attendancePrepDateOptions($program);
        $selectedDate = $this->resolvedSelectedPrepDate($service, $program);

        $table = RegistrationFilamentTableSupport::configureBeneficiaryRowNavigation($table)
            ->poll(fn (): ?string => $this->isRemoteProgram()
                ? $this->attendanceLiveSessionTablePollInterval()
                : null)
            ->modifyQueryUsing(function (Builder $query) use ($service, $program): Builder {
                $query->whereIn('status', [
                    RegistrationStatus::Approved->value,
                    RegistrationStatus::Completed->value,
                ])->with([
                    'user',
                    'attendanceRecords' => fn ($q) => $q->whereIn(
                        'training_date',
                        $service->attendancePrepDateStrings($program),
                    ),
                    'trainingProgram.prepDays',
                ]);

                return $query;
            })
            ->description(fn (): HtmlString => new HtmlString($this->legendHtml()))
            ->headerActions($this->headerActions($service, $program, $prepOptions, $selectedDate))
            ->defaultSort('user.name');

        if ($this->attendanceMode === 'matrix') {
            return $this->configureMatrixTable($table, $service, $program);
        }

        return $this->configureDailyTable($table, $service, $program, $selectedDate);
    }

    /**
     * @param  array<string, string>  $prepOptions
     * @return list<Action>
     */
    protected function headerActions(
        ProgramAttendanceService $service,
        TrainingProgram $program,
        array $prepOptions,
        ?string $selectedDate,
    ): array {
        return [
            Action::make('modeDaily')
                ->label('تحضير يومي')
                ->icon('heroicon-o-calendar-days')
                ->color(fn (): string => $this->attendanceMode === 'daily' ? 'primary' : 'gray')
                ->action(fn () => $this->setAttendanceMode('daily')),

            Action::make('modeMatrix')
                ->label('سجل جميع الأيام')
                ->icon('heroicon-o-table-cells')
                ->color(fn (): string => $this->attendanceMode === 'matrix' ? 'primary' : 'gray')
                ->action(fn () => $this->setAttendanceMode('matrix')),

            Action::make('selectPrepDay')
                ->label('يوم التحضير')
                ->icon('heroicon-o-clock')
                ->visible(fn (): bool => $this->attendanceMode === 'daily' && $prepOptions !== [])
                ->form([
                    Select::make('prep_date')
                        ->label('اختر يوم التحضير')
                        ->options($prepOptions)
                        ->required()
                        ->default($selectedDate)
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    $this->selectedPrepDate = (string) $data['prep_date'];
                    $this->resetTable();
                }),

            Action::make('openGateScan')
                ->label('مسح QR للتحضير')
                ->icon('heroicon-o-qr-code')
                ->color('success')
                ->url(function () use ($selectedDate): string {
                    $params = ['program' => $this->ownerProgram()->slug];
                    if (filled($selectedDate)) {
                        $params['date'] = $selectedDate;
                    }

                    return route('gate.scan', $params);
                })
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

            Action::make('adoptAbsent')
                ->label('اعتماد غياب اليوم')
                ->icon('heroicon-o-user-minus')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('اعتماد غياب اليوم')
                ->modalDescription('سيتم تعليم جميع المستفيدات غير المحدّدات كغائبات لهذا اليوم فقط. لن تُغيَّر الحالات المسجّلة مسبقاً.')
                ->visible(fn (): bool => $this->attendanceMode === 'daily' && filled($selectedDate))
                ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                ->action(function () use ($service, $program, $selectedDate): void {
                    $count = $service->adoptAbsentForUnspecified(
                        $program,
                        (string) $selectedDate,
                        Auth::user(),
                    );

                    Notification::make()
                        ->title($count > 0 ? "تم اعتماد غياب {$count} مستفيدة" : 'لا يوجد غير محدّد لاعتماده')
                        ->success()
                        ->send();

                    $this->resetTable();
                }),
        ];
    }

    protected function configureDailyTable(
        Table $table,
        ProgramAttendanceService $service,
        TrainingProgram $program,
        ?string $selectedDate,
    ): Table {
        $dayLabel = $selectedDate
            ? ($service->attendancePrepDateOptions($program)[$selectedDate] ?? $selectedDate)
            : '—';

        return $table
            ->columns([
                RegistrationFilamentTableSupport::beneficiaryNameColumn(),

                TextColumn::make('day_status')
                    ->label('حالة '.$dayLabel)
                    ->badge()
                    ->getStateUsing(function (ProgramRegistration $record) use ($selectedDate): string {
                        if ($selectedDate === null) {
                            return 'غير محدد';
                        }

                        return $this->statusLabelFor($record, $selectedDate);
                    })
                    ->color(function (ProgramRegistration $record) use ($service, $selectedDate): string {
                        if ($selectedDate === null) {
                            return 'gray';
                        }

                        $status = $service->statusForDate($record, $selectedDate);

                        return $status?->color() ?? 'gray';
                    }),

                SelectColumn::make('quick_status')
                    ->label('تغيير سريع')
                    ->options(AttendanceStatus::options())
                    ->placeholder('غير محدد')
                    ->disabled(fn (): bool => $selectedDate === null || ! (auth()->user()?->can('update', $program) ?? false))
                    ->getStateUsing(function (ProgramRegistration $record) use ($service, $selectedDate): ?string {
                        if ($selectedDate === null) {
                            return null;
                        }

                        return $service->statusForDate($record, $selectedDate)?->value;
                    })
                    ->updateStateUsing(function (ProgramRegistration $record, ?string $state) use ($service, $selectedDate): void {
                        if ($selectedDate === null) {
                            return;
                        }

                        if ($state === null || $state === '') {
                            $service->clearDay($record, $selectedDate, Auth::user());

                            return;
                        }

                        $service->markManualDay(
                            $record,
                            $selectedDate,
                            AttendanceStatus::from($state),
                            null,
                            Auth::user(),
                        );
                    }),

                TextColumn::make('attendance_days')
                    ->label('أيام الحضور')
                    ->getStateUsing(fn (ProgramRegistration $record): string => RegistrationFilamentTableSupport::programAttendanceSummary($record)),

                RegistrationFilamentTableSupport::attendancePercentageColumn(),
            ])
            ->actions([
                Action::make('manualAttendance')
                    ->label('تحضير يدوي')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->authorize('update')
                    ->visible(fn (): bool => $selectedDate !== null)
                    ->form([
                        Select::make('status')
                            ->label('الحالة')
                            ->options(AttendanceStatus::options())
                            ->required()
                            ->default(AttendanceStatus::Present->value),

                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->rows(2),
                    ])
                    ->action(function (ProgramRegistration $record, array $data) use ($service, $selectedDate): void {
                        $service->markManualDay(
                            $record,
                            (string) $selectedDate,
                            AttendanceStatus::from((string) $data['status']),
                            $data['notes'] ?? null,
                            Auth::user(),
                        );

                        Notification::make()->title('تم تحديث الحضور')->success()->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    $this->bulkStatusAction('bulkPresent', 'تحضير الكل حاضر', AttendanceStatus::Present, $selectedDate),
                    $this->bulkStatusAction('bulkAbsent', 'تحضير الكل غائب', AttendanceStatus::Absent, $selectedDate),
                    $this->bulkStatusAction('bulkExcused', 'تحضير الكل بعذر', AttendanceStatus::Excused, $selectedDate),
                    $this->bulkStatusAction('bulkLate', 'تحضير الكل متأخر', AttendanceStatus::Late, $selectedDate),
                    BulkAction::make('bulkReset')
                        ->label('إعادة لغير محدد')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn (): bool => $selectedDate !== null)
                        ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                        ->action(function (EloquentCollection $records) use ($service, $program, $selectedDate): void {
                            $count = $service->bulkClearDay(
                                $program,
                                $records->modelKeys(),
                                (string) $selectedDate,
                                Auth::user(),
                            );

                            Notification::make()
                                ->title("تم إعادة {$count} سجل لغير محدد")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    protected function configureMatrixTable(
        Table $table,
        ProgramAttendanceService $service,
        TrainingProgram $program,
    ): Table {
        $prepDays = $service->attendancePrepDays($program);
        $columns = [
            RegistrationFilamentTableSupport::beneficiaryNameColumn()
                ->extraHeaderAttributes(['class' => 'fi-attendance-sticky-col'])
                ->extraCellAttributes(['class' => 'fi-attendance-sticky-col']),
        ];

        foreach ($prepDays as $day) {
            /** @var ProgramPrepDay $day */
            $date = $day->dateString();
            $label = $day->displayLabel();

            $columns[] = TextColumn::make('day_'.$date)
                ->label($label)
                ->badge()
                ->getStateUsing(fn (ProgramRegistration $record): string => $this->statusLabelFor($record, $date))
                ->color(function (ProgramRegistration $record) use ($service, $date): string {
                    return $service->statusForDate($record, $date)?->color() ?? 'gray';
                });
        }

        $columns[] = TextColumn::make('attended_count')
            ->label('أيام الحضور')
            ->getStateUsing(fn (ProgramRegistration $record): int => $service->countAttendedDays($record));

        $columns[] = TextColumn::make('total_days')
            ->label('إجمالي الأيام')
            ->getStateUsing(fn (): int => $service->countExpectedTrainingDays($program));

        $columns[] = RegistrationFilamentTableSupport::attendancePercentageColumn();

        return $table
            ->columns($columns)
            ->extraAttributes(['class' => 'fi-attendance-matrix-scroll'])
            ->actions([
                Action::make('manualAttendance')
                    ->label('تحضير يدوي')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->authorize('update')
                    ->visible(fn (): bool => $prepDays->isNotEmpty())
                    ->form([
                        Select::make('training_date')
                            ->label('يوم التحضير')
                            ->options($service->attendancePrepDateOptions($program))
                            ->required()
                            ->native(false)
                            ->default($service->defaultPrepDate($program)),

                        Select::make('status')
                            ->label('الحالة')
                            ->options(AttendanceStatus::options())
                            ->required()
                            ->default(AttendanceStatus::Present->value),

                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->rows(2),
                    ])
                    ->action(function (ProgramRegistration $record, array $data) use ($service): void {
                        $service->markManualDay(
                            $record,
                            (string) $data['training_date'],
                            AttendanceStatus::from((string) $data['status']),
                            $data['notes'] ?? null,
                            Auth::user(),
                        );

                        Notification::make()->title('تم تحديث الحضور')->success()->send();
                    }),
            ])
            ->bulkActions([]);
    }

    protected function bulkStatusAction(
        string $name,
        string $label,
        AttendanceStatus $status,
        ?string $selectedDate,
    ): BulkAction {
        return BulkAction::make($name)
            ->label($label)
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->visible(fn (): bool => $selectedDate !== null)
            ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
            ->action(function (EloquentCollection $records) use ($status, $selectedDate): void {
                $count = app(ProgramAttendanceService::class)->bulkMarkDay(
                    $this->ownerProgram(),
                    $records->modelKeys(),
                    (string) $selectedDate,
                    $status,
                    Auth::user(),
                );

                Notification::make()
                    ->title("تم تحديث {$count} مستفيدة ({$status->label()})")
                    ->success()
                    ->send();
            });
    }

    protected function statusLabelFor(ProgramRegistration $record, string $date): string
    {
        $status = app(ProgramAttendanceService::class)->statusForDate($record, $date);

        return $status?->label() ?? 'غير محدد';
    }

    protected function resolvedSelectedPrepDate(
        ProgramAttendanceService $service,
        TrainingProgram $program,
    ): ?string {
        if (
            filled($this->selectedPrepDate)
            && $service->isValidAttendancePrepDate($program, $this->selectedPrepDate)
        ) {
            return $this->selectedPrepDate;
        }

        $this->selectedPrepDate = $service->defaultPrepDate($program);

        return $this->selectedPrepDate;
    }

    protected function legendHtml(): string
    {
        $tz = config('app.timezone');
        $today = Carbon::today($tz)->toDateString();
        $dayHint = $this->attendanceMode === 'daily' && filled($this->selectedPrepDate)
            ? ' · اليوم المحدد: <strong>'.e($this->selectedPrepDate).'</strong>'
            : '';

        $items = [
            ['حاضر', 'success'],
            ['متأخر', 'warning'],
            ['غائب', 'danger'],
            ['بعذر', 'info'],
            ['غير محدد', 'gray'],
        ];

        $badges = collect($items)->map(function (array $item): string {
            [$label, $color] = $item;

            return '<span class="fi-badge fi-color-'.$color.'" style="margin-inline:0.15rem">'.e($label).'</span>';
        })->implode('');

        return '<div class="flex flex-wrap items-center gap-2 text-sm">'
            .'<span>دليل الحالات:</span>'.$badges
            .'<span class="text-gray-500">Timezone: '.e($tz).' · اليوم: '.e($today).$dayHint.'</span>'
            .'</div>';
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
