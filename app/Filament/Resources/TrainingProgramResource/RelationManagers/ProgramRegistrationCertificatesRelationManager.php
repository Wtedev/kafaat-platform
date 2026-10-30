<?php

namespace App\Filament\Resources\TrainingProgramResource\RelationManagers;

use App\Enums\RegistrationStatus;
use App\Enums\SurveyType;
use App\Filament\Support\RegistrationFilamentTableSupport;
use App\Jobs\IssueProgramCertificateJob;
use App\Models\Certificate;
use App\Models\ProgramAttendanceLink;
use App\Models\ProgramAttendanceMark;
use App\Models\ProgramRegistration;
use App\Models\ProgramSurvey;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Certificates\ProgramCertificateEligibility;
use App\Services\Certificates\ProgramCertificateIssuance;
use App\Services\Surveys\ProgramSurveyService;
use App\Support\CertificateAuthorization;
use App\Support\Certificates\CertificateBeneficiaryCount;
use App\Support\PublicDiskPath;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Throwable;

class ProgramRegistrationCertificatesRelationManager extends RelationManager
{
    public const TAB_KEY = 'certificates';

    protected static string $relationship = 'registrations';

    protected static ?string $title = 'الشهادات';

    public int $certificateIssueCount = 0;

    /** @var list<string> */
    public array $certificateIssueNames = [];

    /** @var list<int> */
    public array $openAttendanceLinkIds = [];

    /** @var array<int, array{id: int, issued_on: ?string, file_path: ?string}> */
    public array $issuedCertificatesByUser = [];

    /** @var list<array{attribute: string, label: string, anonymous: bool, id: int}> */
    public array $programSurveyColumns = [];

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
        $this->rememberCertificateTableContext();

        return RegistrationFilamentTableSupport::configureBeneficiaryRowNavigation($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->scopeCertificateRows($query))
            ->description(fn (): HtmlString => new HtmlString(
                view('filament.certificates.program-certificate-stats', [
                    ...$this->certificateStats(),
                    'blocked' => $this->issueBlockedReason(),
                ])->render()
            ))
            ->emptyStateHeading('لا يوجد مستفيدون مقبولون')
            ->emptyStateDescription('يظهر هنا المسجلون المقبولون أو المكتملون.')
            ->columns([
                RegistrationFilamentTableSupport::beneficiaryNameColumn(),
                TextColumn::make('certificate_status')
                    ->label('الشهادة')
                    ->badge()
                    ->getStateUsing(fn (ProgramRegistration $record): string => $this->issuedRow($record) !== null ? 'صادرة' : 'لم تصدر')
                    ->color(fn (ProgramRegistration $record): string => $this->issuedRow($record) !== null ? 'success' : 'gray'),
                TextColumn::make('certificate_issued_on')
                    ->label('تاريخ الإصدار')
                    ->getStateUsing(fn (ProgramRegistration $record): string => $this->issuedRow($record)['issued_on'] ?? '—'),
                TextColumn::make('attendance_days')
                    ->label('الحضور')
                    ->getStateUsing(fn (ProgramRegistration $record): string => ((int) ($record->attended_day_count ?? 0)).' من '.count($this->openAttendanceLinkIds)),
                TextColumn::make('survey_answers')
                    ->label('الاستبيانات')
                    ->getStateUsing(fn (ProgramRegistration $record): string => $this->surveySummary($record)),
            ])
            ->filters($this->certificateFilters())
            ->deferFilters(false)
            ->selectable()
            ->selectCurrentPageOnly(false)
            ->headerActions([
                Action::make('designCertificate')
                    ->label('تخصيص الشهادة')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (): string => route('certificate-designer.edit', $this->ownerProgram()))
                    ->authorize(fn (): bool => $this->canIssueCertificates()),
                Action::make('issueCertificates')
                    ->label('إصدار الشهادات')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->disabled(fn (): bool => ! $this->savedBackgroundExists())
                    ->tooltip(fn (): ?string => $this->issueBlockedReason())
                    ->authorize(fn (): bool => $this->canIssueCertificates())
                    ->modalHeading('إصدار الشهادات')
                    ->modalSubmitActionLabel(fn (): string => CertificateBeneficiaryCount::buttonLabel($this->certificateIssueCount))
                    ->modalSubmitAction(fn (Action $action): Action => $action->disabled($this->certificateIssueCount === 0))
                    ->beforeFormFilled(function (): void {
                        $this->refreshCertificateIssuePreview([]);
                    })
                    ->form($this->issueForm())
                    ->action(function (array $data): void {
                        $this->dispatchCertificates($data);
                    }),
            ])
            ->toolbarActions([
                $this->issueSelectedAction(),
            ])
            ->actions([
                Action::make('downloadCertificate')
                    ->label('تنزيل PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (ProgramRegistration $record): ?string => $this->downloadUrlFor($record))
                    ->openUrlInNewTab()
                    ->visible(fn (ProgramRegistration $record): bool => $this->downloadUrlFor($record) !== null),
                Action::make('resendCertificateEmail')
                    ->label('إعادة إرسال البريد')
                    ->icon('heroicon-o-envelope')
                    ->visible(fn (ProgramRegistration $record): bool => $this->issuedRow($record) !== null && $this->canIssueCertificates())
                    ->requiresConfirmation()
                    ->modalHeading('إعادة إرسال البريد')
                    ->action(function (ProgramRegistration $record): void {
                        $this->resendCertificate($record);
                    }),
            ])
            ->defaultSort('user.name');
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function refreshCertificateIssuePreview(array $state): void
    {
        $registrations = app(ProgramCertificateEligibility::class)->eligible(
            $this->ownerProgram(),
            $this->attendanceIdsFromState($state),
            $this->surveysFromState($state),
        );

        $this->certificateIssueCount = $registrations->count();
        $this->certificateIssueNames = $registrations
            ->map(fn (ProgramRegistration $registration): string => $registration->user?->certificateName() ?? '—')
            ->values()
            ->all();
    }

    private function rememberCertificateTableContext(): void
    {
        $program = $this->ownerProgram();

        $this->openAttendanceLinkIds = ProgramAttendanceLink::query()
            ->where('training_program_id', $program->getKey())
            ->whereNull('cancelled_at')
            ->orderByRaw('case when opens_at is null then 1 else 0 end')
            ->orderBy('opens_at')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->issuedCertificatesByUser = Certificate::query()
            ->where('certificateable_type', $program->getMorphClass())
            ->where('certificateable_id', $program->getKey())
            ->get(['id', 'user_id', 'issued_at', 'file_path'])
            ->mapWithKeys(fn (Certificate $certificate): array => [
                (int) $certificate->user_id => [
                    'id' => (int) $certificate->getKey(),
                    'issued_on' => $certificate->issued_at?->timezone('Asia/Riyadh')->format('Y-m-d'),
                    'file_path' => $certificate->file_path,
                ],
            ])
            ->all();

        $this->programSurveyColumns = ProgramSurvey::query()
            ->where('training_program_id', $program->getKey())
            ->get()
            ->sortBy(fn (ProgramSurvey $survey): int => match ($survey->type) {
                SurveyType::Pre => 1,
                SurveyType::Post => 2,
                SurveyType::Satisfaction => 3,
            })
            ->map(fn (ProgramSurvey $survey): array => [
                'attribute' => 'survey_'.$survey->type->value,
                'label' => $survey->type->label(),
                'anonymous' => (bool) $survey->is_anonymous,
                'id' => (int) $survey->getKey(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Builder<ProgramRegistration>  $query
     * @return Builder<ProgramRegistration>
     */
    private function scopeCertificateRows(Builder $query): Builder
    {
        $query->whereIn('program_registrations.status', [
            RegistrationStatus::Approved->value,
            RegistrationStatus::Completed->value,
        ])->with('user');

        $marks = ProgramAttendanceMark::query()
            ->selectRaw('count(*)')
            ->whereColumn('program_attendance_marks.program_registration_id', 'program_registrations.id');

        if ($this->openAttendanceLinkIds === []) {
            $marks->whereRaw('0 = 1');
        } else {
            $marks->whereIn('program_attendance_marks.program_attendance_link_id', $this->openAttendanceLinkIds);
        }

        $query->addSelect(['attended_day_count' => $marks]);

        foreach ($this->programSurveyColumns as $column) {
            $table = $column['anonymous'] ? 'survey_completions' : 'survey_responses';
            $query->addSelect([
                $column['attribute'] => DB::table($table)
                    ->selectRaw('count(*)')
                    ->whereColumn($table.'.registration_id', 'program_registrations.id')
                    ->where($table.'.program_survey_id', $column['id']),
            ]);
        }

        return $query;
    }

    /**
     * @return array{accepted: int, issued: int, remaining: int}
     */
    private function certificateStats(): array
    {
        $program = $this->ownerProgram();

        return [
            'accepted' => ProgramRegistration::query()
                ->where('training_program_id', $program->getKey())
                ->whereIn('status', [
                    RegistrationStatus::Approved->value,
                    RegistrationStatus::Completed->value,
                ])
                ->count(),
            'issued' => Certificate::query()
                ->where('certificateable_type', $program->getMorphClass())
                ->where('certificateable_id', $program->getKey())
                ->count(),
            'remaining' => app(ProgramCertificateEligibility::class)->count($program),
        ];
    }

    /**
     * @return array<int, Filter|SelectFilter>
     */
    private function certificateFilters(): array
    {
        return [
            SelectFilter::make('attended')
                ->label('الحضور')
                ->options([
                    'present' => 'حاضر',
                    'absent' => 'لم يحضر',
                ])
                ->query(function (Builder $query, array $data): Builder {
                    $value = $data['value'] ?? null;
                    if (! filled($value)) {
                        return $query;
                    }

                    [$sql, $bindings] = $this->attendanceCountExpression();

                    return $query->whereRaw($sql.($value === 'present' ? ' >= 1' : ' = 0'), $bindings);
                }),
            Filter::make('attendance_percent')
                ->label('نسبة الحضور من الأيام')
                ->form([
                    TextInput::make('minimum')
                        ->label('الحد الأدنى %')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->placeholder('مثال: 75'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    if (! filled($data['minimum'] ?? null)) {
                        return $query;
                    }

                    $minimum = max(0, min(100, (int) $data['minimum']));
                    $total = count($this->openAttendanceLinkIds);
                    if ($total === 0) {
                        return $minimum === 0 ? $query : $query->whereRaw('0 = 1');
                    }

                    $needed = (int) ceil($total * $minimum / 100);
                    [$sql, $bindings] = $this->attendanceCountExpression();

                    return $query->whereRaw($sql.' >= ?', [...$bindings, $needed]);
                }),
            $this->surveyFilter('survey_pre', SurveyType::Pre, 'الاستبيان القبلي'),
            $this->surveyFilter('survey_post', SurveyType::Post, 'الاستبيان البعدي'),
            $this->surveyFilter('survey_satisfaction', SurveyType::Satisfaction, 'استبيان الرضا'),
        ];
    }

    private function issueSelectedAction(): BulkAction
    {
        return BulkAction::make('issueSelected')
            ->label('إصدار الشهادات')
            ->icon('heroicon-o-academic-cap')
            ->color('success')
            ->authorize(fn (): bool => $this->canIssueCertificates())
            ->requiresConfirmation()
            ->modalHeading('إصدار الشهادات للمحددين')
            ->modalDescription(function (Collection $records): string {
                $ready = $records->filter(fn (ProgramRegistration $record): bool => $this->issuedRow($record) === null)->count();

                return 'سيُصدر '.$ready.' شهادة من أصل '.$records->count().' محدداً. من صدرت له شهادة يُتجاوز.';
            })
            ->modalSubmitActionLabel('إصدار')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $this->dispatchSelected($records);
            });
    }

    private function dispatchSelected(Collection $records): void
    {
        $actor = auth()->user();
        if (! $actor instanceof User || ! CertificateAuthorization::canIssue($actor)) {
            abort(403);
        }

        if (! $this->savedBackgroundExists()) {
            Notification::make()->title('ارفع خلفية الشهادة أولاً')->warning()->send();

            return;
        }

        $pending = $records->filter(fn (ProgramRegistration $record): bool => $this->issuedRow($record) === null);
        if ($pending->isEmpty()) {
            Notification::make()->title('لا توجد شهادات جديدة بين المحددين')->warning()->send();

            return;
        }

        foreach ($pending as $registration) {
            IssueProgramCertificateJob::dispatch($registration->id, $actor->id);
        }

        app(AuditLogger::class)->record(
            actor: $actor,
            action: 'program_certificates.issued',
            resource: $this->ownerProgram(),
            metadata: [
                'count' => $pending->count(),
                'selected' => true,
                'registration_ids' => $pending->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            ],
        );

        Notification::make()
            ->title('جارٍ إصدار '.$pending->count().' شهادة للمحددين، ستظهر خلال دقائق')
            ->success()
            ->send();
    }

    /**
     * @return array{0: string, 1: list<int>}
     */
    private function attendanceCountExpression(): array
    {
        $ids = $this->openAttendanceLinkIds;
        if ($ids === []) {
            return ['0', []];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        return [
            '(select count(*) from program_attendance_marks where program_attendance_marks.program_registration_id = program_registrations.id and program_attendance_marks.program_attendance_link_id in ('.$placeholders.'))',
            $ids,
        ];
    }

    private function surveyFilter(string $name, SurveyType $type, string $label): SelectFilter
    {
        return SelectFilter::make($name)
            ->label($label)
            ->options([
                'done' => 'مكتمل',
                'missing' => 'غير مكتمل',
            ])
            ->query(function (Builder $query, array $data) use ($type): Builder {
                $value = $data['value'] ?? null;
                if (! filled($value)) {
                    return $query;
                }

                return $this->constrainSurvey($query, $type, $value === 'done');
            });
    }

    /**
     * @param  Builder<ProgramRegistration>  $query
     * @return Builder<ProgramRegistration>
     */
    private function constrainSurvey(Builder $query, SurveyType $type, bool $completed): Builder
    {
        $survey = ProgramSurvey::query()
            ->where('training_program_id', $this->ownerProgram()->getKey())
            ->where('type', $type->value)
            ->first();

        if (! $survey instanceof ProgramSurvey) {
            return $completed ? $query->whereRaw('0 = 1') : $query;
        }

        if ($completed) {
            return app(ProgramSurveyService::class)->whereRegistrationAnswered($query, $survey);
        }

        $table = $survey->is_anonymous ? 'survey_completions' : 'survey_responses';

        return $query->whereNotExists(function ($inner) use ($survey, $table): void {
            $inner->selectRaw('1')
                ->from($table)
                ->whereColumn($table.'.registration_id', 'program_registrations.id')
                ->where($table.'.program_survey_id', $survey->getKey());
        });
    }

    /**
     * @return array<int, mixed>
     */
    private function issueForm(): array
    {
        return [
            CheckboxList::make('attendance_link_ids')
                ->label('أيام الحضور')
                ->options(fn (): array => $this->attendanceOptions())
                ->helperText('اترك الأيام فارغة إذا لم ترد اشتراط الحضور.')
                ->visible(fn (): bool => $this->attendanceOptions() !== [])
                ->live()
                ->afterStateUpdated(function (Get $get): void {
                    $this->rememberIssueCriteria($get);
                }),
            Checkbox::make('require_pre')
                ->label('يشترط إكمال الاستبيان القبلي')
                ->visible(fn (): bool => $this->surveyExists(SurveyType::Pre))
                ->live()
                ->afterStateUpdated(function (Get $get): void {
                    $this->rememberIssueCriteria($get);
                }),
            Checkbox::make('require_post')
                ->label('يشترط إكمال الاستبيان البعدي')
                ->visible(fn (): bool => $this->surveyExists(SurveyType::Post))
                ->live()
                ->afterStateUpdated(function (Get $get): void {
                    $this->rememberIssueCriteria($get);
                }),
            Checkbox::make('require_satisfaction')
                ->label('يشترط إكمال استبيان الرضا')
                ->visible(fn (): bool => $this->surveyExists(SurveyType::Satisfaction))
                ->live()
                ->afterStateUpdated(function (Get $get): void {
                    $this->rememberIssueCriteria($get);
                }),
            Placeholder::make('eligible_names')
                ->label('المستفيدون')
                ->content(fn (): HtmlString => new HtmlString($this->eligibleNamesMarkup())),
        ];
    }

    private function rememberIssueCriteria(Get $get): void
    {
        $this->refreshCertificateIssuePreview([
            'attendance_link_ids' => $get('attendance_link_ids') ?? [],
            'require_pre' => (bool) $get('require_pre'),
            'require_post' => (bool) $get('require_post'),
            'require_satisfaction' => (bool) $get('require_satisfaction'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dispatchCertificates(array $data): void
    {
        $actor = auth()->user();
        if (! $actor instanceof User || ! CertificateAuthorization::canIssue($actor)) {
            abort(403);
        }

        if (! $this->savedBackgroundExists()) {
            Notification::make()->title('احفظ تصميم الشهادة أولاً')->warning()->send();

            return;
        }

        $attendanceIds = $this->attendanceIdsFromState($data);
        $surveys = $this->surveysFromState($data);
        $registrations = app(ProgramCertificateEligibility::class)->eligible(
            $this->ownerProgram(),
            $attendanceIds,
            $surveys,
        );

        $this->certificateIssueCount = $registrations->count();
        $this->certificateIssueNames = $registrations
            ->map(fn (ProgramRegistration $registration): string => $registration->user?->certificateName() ?? '—')
            ->values()
            ->all();

        if ($registrations->isEmpty()) {
            Notification::make()->title('لا يوجد مستفيدون مؤهلون')->warning()->send();

            return;
        }

        foreach ($registrations as $registration) {
            IssueProgramCertificateJob::dispatch($registration->id, $actor->id);
        }

        app(AuditLogger::class)->record(
            actor: $actor,
            action: 'program_certificates.issued',
            resource: $this->ownerProgram(),
            metadata: [
                'count' => $registrations->count(),
                'attendance_link_ids' => $attendanceIds,
                'survey_types' => array_map(fn (SurveyType $type): string => $type->value, $surveys),
                'registration_ids' => $registrations->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            ],
        );

        Notification::make()
            ->title('جارٍ إصدار '.$registrations->count().' شهادة، ستظهر للمستفيدين خلال دقائق')
            ->success()
            ->send();
    }

    private function resendCertificate(ProgramRegistration $registration): void
    {
        $row = $this->issuedRow($registration);
        if ($row === null) {
            return;
        }

        $certificate = Certificate::query()->find($row['id']);
        if ($certificate === null) {
            return;
        }

        $actor = auth()->user();

        try {
            app(ProgramCertificateIssuance::class)->resend($certificate, $actor instanceof User ? $actor : null);
            Notification::make()->title('أُعيد إرسال البريد')->success()->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('تعذر إرسال البريد')->danger()->send();
        }
    }

    private function eligibleNamesMarkup(): string
    {
        if ($this->certificateIssueNames === []) {
            return '<p>لا يوجد مستفيدون مؤهلون</p>';
        }

        $items = collect($this->certificateIssueNames)
            ->map(fn (string $name): string => '<li>'.e($name).'</li>')
            ->implode('');

        return '<details><summary>الأسماء ('.count($this->certificateIssueNames).')</summary><ul>'.$items.'</ul></details>';
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<int>
     */
    private function attendanceIdsFromState(array $state): array
    {
        $ids = $state['attendance_link_ids'] ?? [];
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_map(intval(...), $ids)));
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<SurveyType>
     */
    private function surveysFromState(array $state): array
    {
        $selected = [];
        foreach ([
            'require_pre' => SurveyType::Pre,
            'require_post' => SurveyType::Post,
            'require_satisfaction' => SurveyType::Satisfaction,
        ] as $key => $type) {
            if (! ($state[$key] ?? false) || ! $this->surveyExists($type)) {
                continue;
            }

            $selected[] = $type;
        }

        return $selected;
    }

    /**
     * @return array<int|string, string>
     */
    private function attendanceOptions(): array
    {
        return ProgramAttendanceLink::query()
            ->where('training_program_id', $this->ownerProgram()->getKey())
            ->whereNull('cancelled_at')
            ->orderByRaw('case when opens_at is null then 1 else 0 end')
            ->orderBy('opens_at')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (ProgramAttendanceLink $link): array => [
                $link->getKey() => filled($link->name) ? (string) $link->name : 'يوم '.$link->getKey(),
            ])
            ->all();
    }

    private function surveyExists(SurveyType $type): bool
    {
        return ProgramSurvey::query()
            ->where('training_program_id', $this->ownerProgram()->getKey())
            ->where('type', $type->value)
            ->exists();
    }

    private function surveySummary(ProgramRegistration $registration): string
    {
        if ($this->programSurveyColumns === []) {
            return '—';
        }

        return collect($this->programSurveyColumns)
            ->map(function (array $column) use ($registration): string {
                $answered = (int) ($registration->{$column['attribute']} ?? 0) > 0;

                return $column['label'].' '.($answered ? '✓' : '✗');
            })
            ->implode('  ');
    }

    /**
     * @return array{id: int, issued_on: ?string, file_path: ?string}|null
     */
    private function issuedRow(ProgramRegistration $registration): ?array
    {
        return $this->issuedCertificatesByUser[(int) $registration->user_id] ?? null;
    }

    private function downloadUrlFor(ProgramRegistration $registration): ?string
    {
        $row = $this->issuedRow($registration);
        if ($row === null) {
            return null;
        }

        $relative = PublicDiskPath::normalize($row['file_path']);
        if ($relative === null || str_starts_with($relative, 'http://') || str_starts_with($relative, 'https://')) {
            return null;
        }

        if (! Storage::disk('public')->exists($relative)) {
            return null;
        }

        return route('certificates.download', ['certificate' => $row['id']]);
    }

    private function savedBackgroundExists(): bool
    {
        $template = $this->ownerProgram()->certificateTemplate;
        if ($template === null || ! filled($template->background_path)) {
            return false;
        }

        return Storage::disk('public')->exists($template->background_path);
    }

    /**
     * Why issuing is blocked, or null when it is allowed. A saved design whose
     * file has gone missing gets its own message so staff know to upload it again.
     */
    private function issueBlockedReason(): ?string
    {
        if ($this->savedBackgroundExists()) {
            return null;
        }

        return filled($this->ownerProgram()->certificateTemplate?->background_path)
            ? 'ملف تصميم الشهادة مفقود، أعد رفعه'
            : 'احفظ تصميم الشهادة أولاً';
    }

    private function canIssueCertificates(): bool
    {
        $user = auth()->user();

        return $user instanceof User && CertificateAuthorization::canIssue($user);
    }

    private function ownerProgram(): TrainingProgram
    {
        $record = $this->getOwnerRecord();
        if (! $record instanceof TrainingProgram) {
            throw new \RuntimeException('Certificate tab requires a training program.');
        }

        return $record;
    }
}
