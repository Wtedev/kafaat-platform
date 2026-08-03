<?php

namespace App\Filament\Resources\TrainingProgramResource\RelationManagers;

use App\Enums\ProgramPrepDayType;
use App\Enums\RegistrationStatus;
use App\Filament\Concerns\InteractsWithAttendanceLiveSession;
use App\Filament\Support\RegistrationFilamentTableSupport;
use App\Models\AttendanceLiveSession;
use App\Models\ProgramPrepDay;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Services\AttendanceLiveSessionService;
use App\Services\ProgramAttendanceService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

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
        $selectedDay = $selectedDate ? $service->prepDayForDate($program, $selectedDate) : null;
        $today = Carbon::today(config('app.timezone'))->toDateString();

        $table = RegistrationFilamentTableSupport::configureBeneficiaryRowNavigation($table)
            ->poll(fn (): ?string => $this->shouldPollLiveSession($selectedDate, $today)
                ? $this->attendanceLiveSessionTablePollInterval()
                : null)
            ->modifyQueryUsing(function (Builder $query): Builder {
                $query->whereIn('status', [
                    RegistrationStatus::Approved->value,
                    RegistrationStatus::Completed->value,
                ])->with([
                    'user',
                    'attendanceRecords',
                    'trainingProgram.prepDays',
                ]);

                return $query;
            })
            ->description(fn (): ?HtmlString => $this->headerHintHtml($selectedDay, $selectedDate))
            ->headerActions($this->headerActions($service, $program, $prepOptions, $selectedDate, $selectedDay, $today))
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
        ?ProgramPrepDay $selectedDay,
        string $today,
    ): array {
        return [
            Action::make('modeDaily')
                ->label('تحضير يومي')
                ->color(fn (): string => $this->attendanceMode === 'daily' ? 'primary' : 'gray')
                ->action(fn () => $this->setAttendanceMode('daily')),

            Action::make('modeMatrix')
                ->label('سجل جميع الأيام')
                ->color(fn (): string => $this->attendanceMode === 'matrix' ? 'primary' : 'gray')
                ->action(fn () => $this->setAttendanceMode('matrix')),

            Action::make('selectPrepDay')
                ->label('اختر اليوم')
                ->visible(fn (): bool => $this->attendanceMode === 'daily' && $prepOptions !== [])
                ->form([
                    Select::make('prep_date')
                        ->label('يوم البرنامج')
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
                ->label('مسح QR')
                ->icon('heroicon-o-qr-code')
                ->color('success')
                ->url(fn (): string => route('gate.scan', ['program' => $this->ownerProgram()->slug]))
                ->openUrlInNewTab()
                ->visible(fn (): bool => $this->attendanceMode === 'daily' && $this->isSelectedTodayInPerson())
                ->authorize(fn (): bool => auth()->user()?->can('viewOperational', $this->getOwnerRecord()) ?? false),

            Action::make('startLiveSession')
                ->label('فتح التحضير')
                ->icon('heroicon-o-signal')
                ->color('success')
                ->visible(fn (): bool => $this->attendanceMode === 'daily'
                    && $this->isSelectedTodayRemote()
                    && $this->activeAttendanceSession() === null)
                ->authorize(fn (): bool => auth()->user()?->can('viewOperational', $this->getOwnerRecord()) ?? false)
                ->action(function (): void {
                    try {
                        $this->startAttendanceLiveSession();
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('تعذّر فتح التحضير')
                            ->body(collect($exception->errors())->flatten()->first() ?? 'حدث خطأ.')
                            ->danger()
                            ->send();
                    }
                }),

            $this->makeAttendanceLiveSessionCountdownAction()
                ->visible(fn (): bool => $this->attendanceMode === 'daily'
                    && $this->isSelectedTodayRemote()
                    && ($this->activeAttendanceSession()?->isActive() ?? false)),
        ];
    }

    protected function isSelectedTodayInPerson(): bool
    {
        $service = app(ProgramAttendanceService::class);
        $program = $this->ownerProgram();
        $selected = $this->resolvedSelectedPrepDate($service, $program);
        $today = Carbon::today(config('app.timezone'))->toDateString();

        if ($selected === null || $selected !== $today) {
            return false;
        }

        return $service->prepDayForDate($program, $selected)?->delivery_type === ProgramPrepDayType::InPerson;
    }

    protected function isSelectedTodayRemote(): bool
    {
        $service = app(ProgramAttendanceService::class);
        $program = $this->ownerProgram();
        $selected = $this->resolvedSelectedPrepDate($service, $program);
        $today = Carbon::today(config('app.timezone'))->toDateString();

        if ($selected === null || $selected !== $today) {
            return false;
        }

        return $service->prepDayForDate($program, $selected)?->delivery_type === ProgramPrepDayType::Remote;
    }

    protected function configureDailyTable(
        Table $table,
        ProgramAttendanceService $service,
        TrainingProgram $program,
        ?string $selectedDate,
    ): Table {
        return $table
            ->columns([
                RegistrationFilamentTableSupport::beneficiaryNameColumn(),

                ToggleColumn::make('is_present')
                    ->label('حاضر')
                    ->disabled(fn (): bool => $selectedDate === null || ! (auth()->user()?->can('update', $program) ?? false))
                    ->getStateUsing(function (ProgramRegistration $record) use ($service, $selectedDate): bool {
                        if ($selectedDate === null) {
                            return false;
                        }

                        return $service->isPresentOnDate($record, $selectedDate);
                    })
                    ->updateStateUsing(function (ProgramRegistration $record, bool $state) use ($service, $selectedDate): void {
                        if ($selectedDate === null) {
                            return;
                        }

                        $service->setPresentState($record, $selectedDate, $state, Auth::user());
                    }),

                TextColumn::make('day_status')
                    ->label('الحالة')
                    ->badge()
                    ->getStateUsing(function (ProgramRegistration $record) use ($service, $selectedDate): string {
                        if ($selectedDate === null) {
                            return '—';
                        }

                        return $service->displayLabelForDate($record, $selectedDate);
                    })
                    ->color(function (ProgramRegistration $record) use ($service, $selectedDate): string {
                        if ($selectedDate === null) {
                            return 'gray';
                        }

                        return $service->isPresentOnDate($record, $selectedDate) ? 'success' : 'gray';
                    }),

                TextColumn::make('attendance_days')
                    ->label('أيام الحضور')
                    ->getStateUsing(fn (ProgramRegistration $record): string => RegistrationFilamentTableSupport::programAttendanceSummary($record)),

                RegistrationFilamentTableSupport::attendancePercentageColumn(),
            ])
            ->actions([])
            ->bulkActions([]);
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
                ->getStateUsing(fn (ProgramRegistration $record): string => $service->displayLabelForDate($record, $date))
                ->color(fn (ProgramRegistration $record): string => $service->isPresentOnDate($record, $date) ? 'success' : 'gray');
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
            ->actions([])
            ->bulkActions([]);
    }

    protected function headerHintHtml(?ProgramPrepDay $selectedDay, ?string $selectedDate): ?HtmlString
    {
        if ($this->attendanceMode !== 'daily' || $selectedDate === null) {
            return null;
        }

        $typeLabel = $selectedDay?->delivery_type?->label() ?? '—';
        $typeColor = $selectedDay?->delivery_type === ProgramPrepDayType::InPerson ? 'success' : 'gray';

        return new HtmlString(
            '<div class="flex flex-wrap items-center gap-2 text-sm">'
            .'<span>اليوم:</span>'
            .'<strong>'.e($selectedDay?->displayLabel() ?? $selectedDate).'</strong>'
            .'<span class="fi-badge fi-color-'.$typeColor.'">'.e($typeLabel).'</span>'
            .'</div>'
        );
    }

    protected function shouldPollLiveSession(?string $selectedDate, string $today): bool
    {
        return $this->attendanceMode === 'daily'
            && $selectedDate === $today
            && app(ProgramAttendanceService::class)->isTodayRemotePrepDay($this->ownerProgram());
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

    protected function ownerProgram(): TrainingProgram
    {
        $program = $this->getOwnerRecord();
        assert($program instanceof TrainingProgram);

        return $program;
    }

    public function activeAttendanceSession(): ?AttendanceLiveSession
    {
        $today = Carbon::today(config('app.timezone'))->toDateString();

        return app(AttendanceLiveSessionService::class)
            ->activeSessionFor($this->getOwnerRecord(), $today);
    }
}
