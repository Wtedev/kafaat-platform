<?php

namespace App\Filament\Resources\TrainingProgramResource\RelationManagers;

use App\Data\Certificates\EligibilityResult;
use App\Enums\CertificateEligibilityStatus;
use App\Enums\CertificatePdfStatus;
use App\Enums\CertificateTemplateStatus;
use App\Enums\RegistrationStatus;
use App\Exports\CertificateRosterExport;
use App\Filament\Resources\TrainingProgramResource\Pages\ManageCertificateDesign;
use App\Jobs\EmailIssuedCertificatesJob;
use App\Jobs\ExportCertificatesZipJob;
use App\Jobs\RegenerateCertificatePdfJob;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Certificates\CertificateDesignService;
use App\Services\Certificates\CertificateEligibilityService;
use App\Services\Certificates\CertificateIssuanceService;
use App\Services\Certificates\CertificateIssueBatch;
use App\Services\ProgramAttendanceService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;

class ProgramCertificatesRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'الشهادات';

    public ?string $activeBatchId = null;

    private bool $pageHydrated = false;

    /** @var list<int> */
    private array $hydratedIds = [];

    /** @var array<int, EligibilityResult> */
    private array $eligibilityByRegistration = [];

    /** @var array<int, Certificate|null> */
    private array $certificatesByRegistration = [];

    /** @var array<int, float|null> */
    private array $attendanceByRegistration = [];

    /** @var array<int, string|null> */
    private array $metricLabels = [];

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return static::ownerIsVisible($user, $ownerRecord);
    }

    protected static function ownerIsVisible(User $user, Model $owner): bool
    {
        return $owner instanceof TrainingProgram && $user->can('viewOperational', $owner);
    }

    protected function designPageClass(): string
    {
        return ManageCertificateDesign::class;
    }

    public function mount(): void
    {
        parent::mount();
        $this->activeBatchId = Cache::get($this->batchCacheKey());
        $this->refreshBatch();
    }

    public function refreshBatch(): void
    {
        if ($this->activeBatchId === null) {
            return;
        }

        $batch = Bus::findBatch($this->activeBatchId);
        if ($batch === null || $batch->finished()) {
            Cache::forget($this->batchCacheKey());
            $this->activeBatchId = null;
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            SchemaView::make('filament.resources.training-program-resource.relation-managers.certificate-status-bar')
                ->viewData(fn (): array => ['banner' => $this->designBanner()]),
            $this->getTabsContentComponent(),
            RenderHook::make(PanelsRenderHook::RESOURCE_RELATION_MANAGER_BEFORE),
            EmbeddedTable::make(),
            RenderHook::make(PanelsRenderHook::RESOURCE_RELATION_MANAGER_AFTER),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function designBanner(): array
    {
        $program = $this->activity();
        $template = $program->certificateTemplate;
        $ready = $this->templateIsReady($template);
        $counts = $ready ? $this->bannerCounts() : ['eligible' => 0, 'issued' => 0, 'awaiting' => 0];

        return [
            'ready' => $ready,
            'summary' => $ready ? $template->eligibility->summarySentence() : null,
            'thumbnail' => $ready ? app(CertificateDesignService::class)->backgroundUrl($template) : null,
            'designUrl' => $this->designPageClass()::getUrl(['record' => $program]),
            'eligible' => $counts['eligible'],
            'issued' => $counts['issued'],
            'awaiting' => $counts['awaiting'],
            'polling' => $this->activeBatchId !== null,
            'progress' => $this->batchProgressLabel(),
        ];
    }

    public function getTableRecords(): Collection|Paginator|CursorPaginator
    {
        $records = parent::getTableRecords();
        $collection = $this->unwrap($records);
        $ids = $collection->map(fn (Model $record): int => (int) $record->getKey())->sort()->values()->all();

        if (! $this->pageHydrated || $ids !== $this->hydratedIds) {
            $this->hydratePage($collection);
            $this->pageHydrated = true;
            $this->hydratedIds = $ids;
        }

        return $records;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->whereIn('status', [
                    RegistrationStatus::Approved->value,
                    RegistrationStatus::Completed->value,
                ])
                ->with('user'))
            ->columns([
                TextColumn::make('user.name')
                    ->label('اسم المستفيد')
                    ->searchable(),
                ...$this->metricColumns(),
                TextColumn::make('eligibility_label')
                    ->label('حالة الأحقية')
                    ->badge()
                    ->state(fn (Model $record): string => $this->eligibilityLabel($record))
                    ->color(fn (Model $record): string => $this->eligibilityColor($record))
                    ->tooltip(fn (Model $record): ?string => $this->eligibilityTooltip($record)),
                TextColumn::make('certificate_state')
                    ->label('حالة الشهادة')
                    ->badge()
                    ->state(fn (Model $record): string => $this->certificateStateLabel($record))
                    ->color(fn (Model $record): string => $this->certificateStateColor($record)),
                TextColumn::make('certificate_number')
                    ->label('رقم الشهادة')
                    ->state(fn (Model $record): string => $this->certificateFor($record)?->certificate_number ?? '—'),
                TextColumn::make('certificate_issued_at')
                    ->label('تاريخ الإصدار')
                    ->state(fn (Model $record): string => $this->certificateFor($record)?->issued_at?->format('Y/m/d') ?? '—'),
            ])
            ->filters([
                SelectFilter::make('eligibility')
                    ->label('حالة الأحقية')
                    ->options([
                        CertificateEligibilityStatus::Eligible->value => 'مؤهل',
                        CertificateEligibilityStatus::NotEligible->value => 'غير مؤهل',
                        CertificateEligibilityStatus::AwaitingData->value => 'بانتظار البيانات',
                    ])
                    ->query(function (Builder $query, array $data): void {
                        $value = $data['value'] ?? null;
                        if (! is_string($value) || $value === '') {
                            return;
                        }

                        $query->whereIn('id', $this->registrationIdsForEligibility($value));
                    }),
                SelectFilter::make('certificate_state')
                    ->label('حالة الشهادة')
                    ->options([
                        'missing' => 'لم تُصدر',
                        'pending' => 'قيد التوليد',
                        'generated' => 'صادرة',
                        'failed' => 'فشل التوليد',
                    ])
                    ->query(function (Builder $query, array $data): void {
                        $value = $data['value'] ?? null;
                        if (! is_string($value) || $value === '') {
                            return;
                        }

                        $this->applyCertificateStateFilter($query, $value);
                    }),
            ])
            ->headerActions([
                Action::make('design')
                    ->label(fn (): string => $this->templateIsReady() ? 'تعديل التصميم' : 'تعيين التصميم')
                    ->url(fn (): string => $this->designPageClass()::getUrl(['record' => $this->activity()])),
                Action::make('issueEligible')
                    ->label('إصدار الشهادات للمؤهلين')
                    ->color('success')
                    ->disabled(fn (): bool => ! $this->templateIsReady())
                    ->tooltip(fn (): ?string => $this->templateIsReady() ? null : 'لم يُعتمد تصميم الشهادة بعد')
                    ->requiresConfirmation()
                    ->modalDescription(function (): string {
                        $preview = app(CertificateIssueBatch::class)->preview($this->activity());

                        return 'سيتم إصدار '.$preview['new'].' شهادة جديدة. '.$preview['existing'].' مستفيد لديهم شهادة مسبقاً ولن يتأثروا';
                    })
                    ->action(function (): void {
                        $this->startIssueBatch();
                    }),
                Action::make('exportZip')
                    ->label('تصدير الشهادات (ZIP)')
                    ->action(function (): void {
                        ExportCertificatesZipJob::dispatch($this->activity()->getKey(), (int) auth()->id(), null, $this->activity()::class);
                        Notification::make()->title('بدأ تجهيز ملف الشهادات')->success()->send();
                    }),
                Action::make('exportExcel')
                    ->label('تصدير كشف Excel')
                    ->action(function () {
                        return Excel::download(
                            new CertificateRosterExport($this->rosterRows()),
                            'كشف-الشهادات.xlsx',
                        );
                    }),
                Action::make('emailCertificates')
                    ->label('إرسال الشهادات بالبريد')
                    ->disabled(fn (): bool => ! $this->templateIsReady())
                    ->tooltip(fn (): ?string => $this->templateIsReady() ? null : 'لم يُعتمد تصميم الشهادة بعد')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        EmailIssuedCertificatesJob::dispatch(
                            (int) $this->activity()->getKey(),
                            auth()->id() ? (int) auth()->id() : null,
                            null,
                            $this->activity()::class,
                        );
                        Notification::make()->title('بدأ إرسال الشهادات التي لم تُرسل بعد')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('issue')
                    ->label('إصدار')
                    ->visible(fn (Model $record): bool => $this->certificateFor($record) === null && $this->resultFor($record)->eligible)
                    ->disabled(fn (): bool => ! $this->templateIsReady())
                    ->tooltip(fn (): ?string => $this->templateIsReady() ? null : 'لم يُعتمد تصميم الشهادة بعد')
                    ->action(function (Model $record): void {
                        $certificate = app(CertificateIssuanceService::class)->issueForRegistration($record, auth()->user());
                        if ($certificate === null) {
                            Notification::make()
                                ->title('لم تُصدر الشهادة')
                                ->body(implode(' — ', $this->resultFor($record)->reasons))
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()->title('تم إصدار الشهادة')->success()->send();
                    }),
                Action::make('issueExceptional')
                    ->label('إصدار استثنائي')
                    ->color('warning')
                    ->visible(fn (Model $record): bool => auth()->user()?->isAdmin() === true
                        && $this->certificateFor($record) === null
                        && ! $this->resultFor($record)->eligible)
                    ->disabled(fn (): bool => ! $this->templateIsReady())
                    ->tooltip(fn (): ?string => $this->templateIsReady() ? null : 'لم يُعتمد تصميم الشهادة بعد')
                    ->schema([
                        Textarea::make('reason')->label('سبب الإصدار الاستثنائي')->required(),
                    ])
                    ->action(function (Model $record, array $data): void {
                        app(CertificateIssuanceService::class)->issueExceptional($record, auth()->user(), (string) $data['reason']);
                        Notification::make()->title('تم الإصدار الاستثنائي')->success()->send();
                    }),
                Action::make('download')
                    ->label('تحميل')
                    ->url(fn (Model $record): ?string => ($certificate = $this->certificateFor($record))
                        ? route('certificates.download', $certificate)
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (Model $record): bool => $this->certificateFor($record)?->pdf_status === CertificatePdfStatus::Generated
                        && filled($this->certificateFor($record)?->file_path)),
                Action::make('verify')
                    ->label('فتح رابط التحقق')
                    ->url(fn (Model $record): ?string => ($certificate = $this->certificateFor($record))
                        ? route('certificates.verify', $certificate->verification_code)
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (Model $record): bool => $this->certificateFor($record) !== null),
                Action::make('regenerate')
                    ->label('إعادة التوليد')
                    ->visible(fn (Model $record): bool => $this->needsRegeneration($record))
                    ->action(function (Model $record): void {
                        $certificate = $this->certificateFor($record);
                        if ($certificate === null) {
                            return;
                        }

                        RegenerateCertificatePdfJob::dispatch($certificate->id);
                        Notification::make()->title('بدأت إعادة توليد الشهادة')->success()->send();
                    }),
                Action::make('revoke')
                    ->label('إلغاء الشهادة')
                    ->color('danger')
                    ->visible(fn (Model $record): bool => auth()->user()?->isAdmin() === true && $this->certificateFor($record) !== null)
                    ->schema([
                        Textarea::make('reason')->label('سبب الإلغاء')->required(),
                    ])
                    ->action(function (Model $record, array $data): void {
                        $certificate = $this->certificateFor($record);
                        if ($certificate === null) {
                            return;
                        }

                        app(CertificateIssuanceService::class)->revoke($certificate, auth()->user(), (string) $data['reason']);
                        Notification::make()->title('أُلغيت الشهادة')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkIssue')
                        ->label('إصدار')
                        ->disabled(fn (): bool => ! $this->templateIsReady())
                        ->tooltip(fn (): ?string => $this->templateIsReady() ? null : 'لم يُعتمد تصميم الشهادة بعد')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $batchId = app(CertificateIssueBatch::class)->dispatch($this->activity(), auth()->user(), $records);
                            $this->rememberBatch($batchId);
                            Notification::make()->title($batchId ? 'بدأ إصدار الشهادات المحددة' : 'لا توجد شهادات جديدة للإصدار')->send();
                        }),
                    BulkAction::make('bulkZip')
                        ->label('تصدير ZIP')
                        ->action(function (Collection $records): void {
                            $this->hydratePage($records);
                            $ids = $records
                                ->map(fn (Model $record): ?int => $this->certificateFor($record)?->id)
                                ->filter()
                                ->values()
                                ->all();
                            ExportCertificatesZipJob::dispatch((int) $this->activity()->getKey(), (int) auth()->id(), $ids, $this->activity()::class);
                            Notification::make()->title('بدأ تجهيز ملف الشهادات المحددة')->success()->send();
                        }),
                    BulkAction::make('bulkEmail')
                        ->label('إرسال بريد')
                        ->action(function (Collection $records): void {
                            $this->hydratePage($records);
                            $ids = $records
                                ->map(fn (Model $record): ?int => $this->certificateFor($record)?->id)
                                ->filter()
                                ->values()
                                ->all();
                            EmailIssuedCertificatesJob::dispatch((int) $this->activity()->getKey(), auth()->id() ? (int) auth()->id() : null, $ids, $this->activity()::class);
                            Notification::make()->title('بدأ إرسال الشهادات المحددة')->success()->send();
                        }),
                ]),
            ]);
    }

    private function startIssueBatch(): void
    {
        $preview = app(CertificateIssueBatch::class)->preview($this->activity());
        if ($preview['new'] === 0) {
            Notification::make()->title('لا توجد شهادات جديدة للإصدار')->warning()->send();

            return;
        }

        $batchId = app(CertificateIssueBatch::class)->dispatch($this->activity(), auth()->user());
        $this->rememberBatch($batchId);
        Notification::make()->title('بدأ إصدار الشهادات للمؤهلين')->success()->send();
    }

    private function rememberBatch(?string $batchId): void
    {
        if ($batchId === null) {
            return;
        }

        Cache::put($this->batchCacheKey(), $batchId, now()->addHour());
        $this->activeBatchId = $batchId;
    }

    private function batchCacheKey(): string
    {
        return 'certificate-issue-batch:'.$this->activity()->getKey().':'.auth()->id();
    }

    private function batchProgressLabel(): ?string
    {
        if ($this->activeBatchId === null) {
            return null;
        }

        $batch = Bus::findBatch($this->activeBatchId);
        if ($batch === null) {
            return null;
        }

        return 'تقدم الإصدار: '.$batch->processedJobs().' / '.$batch->totalJobs;
    }

    /**
     * @param  Collection<int, Model>  $records
     */
    private function hydratePage(Collection $records): void
    {
        $registrations = $records->values();
        if ($registrations->isEmpty()) {
            return;
        }

        $results = app(CertificateEligibilityService::class)->evaluateMany($registrations);
        $attendance = $registrations->first() instanceof ProgramRegistration
            ? app(ProgramAttendanceService::class)->percentagesForRegistrations($registrations)
            : [];
        $labels = $registrations->first() instanceof ProgramRegistration
            ? []
            : app(CertificateEligibilityService::class)->progressLabels($registrations);
        $program = $this->activity();
        $userIds = $registrations->pluck('user_id')->map(fn ($id): int => (int) $id)->all();
        $certificates = $userIds === []
            ? collect()
            : Certificate::query()
                ->active()
                ->where('certificateable_type', $program->getMorphClass())
                ->where('certificateable_id', $program->getKey())
                ->whereIn('user_id', $userIds)
                ->get()
                ->keyBy(fn (Certificate $certificate): int => (int) $certificate->user_id);

        foreach ($registrations as $registration) {
            $id = (int) $registration->getKey();
            $result = $results->get($registration->getKey()) ?? $results->get($id) ?? EligibilityResult::notConfigured();
            $this->eligibilityByRegistration[$id] = $result;
            $this->certificatesByRegistration[$id] = $certificates->get((int) $registration->user_id);
            $this->attendanceByRegistration[$id] = $attendance[$id] ?? $attendance[$registration->getKey()] ?? null;
            $this->metricLabels[$id] = $labels[$id] ?? null;
        }
    }

    private function resultFor(Model $record): EligibilityResult
    {
        $id = (int) $record->getKey();
        if (array_key_exists($id, $this->eligibilityByRegistration)) {
            return $this->eligibilityByRegistration[$id];
        }

        if ($this->pageHydrated) {
            return EligibilityResult::notConfigured();
        }

        return app(CertificateEligibilityService::class)->evaluate($record);
    }

    private function certificateFor(Model $record): ?Certificate
    {
        $id = (int) $record->getKey();
        if (array_key_exists($id, $this->certificatesByRegistration)) {
            return $this->certificatesByRegistration[$id];
        }

        if ($this->pageHydrated) {
            return null;
        }

        return Certificate::query()
            ->active()
            ->where('user_id', $record->user_id)
            ->where('certificateable_type', $this->activity()->getMorphClass())
            ->where('certificateable_id', $this->activity()->getKey())
            ->first();
    }

    private function attendanceLabel(Model $record): string
    {
        $id = (int) $record->getKey();
        $value = $this->attendanceByRegistration[$id] ?? null;
        if ($value === null) {
            return '—';
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.').'%';
    }

    private function eligibilityLabel(Model $record): string
    {
        return match ($this->resultFor($record)->status) {
            CertificateEligibilityStatus::Eligible => 'مؤهل',
            CertificateEligibilityStatus::NotEligible => 'غير مؤهل',
            CertificateEligibilityStatus::AwaitingData => 'بانتظار البيانات',
            CertificateEligibilityStatus::NotConfigured => 'لم يُضبط قالب الشهادة بعد',
        };
    }

    private function eligibilityColor(Model $record): string
    {
        return match ($this->resultFor($record)->status) {
            CertificateEligibilityStatus::Eligible => 'success',
            CertificateEligibilityStatus::NotEligible => 'danger',
            CertificateEligibilityStatus::AwaitingData => 'warning',
            CertificateEligibilityStatus::NotConfigured => 'gray',
        };
    }

    private function eligibilityTooltip(Model $record): ?string
    {
        $reasons = $this->resultFor($record)->reasons;

        return $reasons === [] ? null : implode(' — ', $reasons);
    }

    private function certificateStateLabel(Model $record): string
    {
        return match ($this->certificateFor($record)?->pdf_status) {
            null => 'لم تُصدر',
            CertificatePdfStatus::Pending => 'قيد التوليد',
            CertificatePdfStatus::Generated => 'صادرة',
            CertificatePdfStatus::Failed => 'فشل التوليد',
        };
    }

    private function certificateStateColor(Model $record): string
    {
        return match ($this->certificateFor($record)?->pdf_status) {
            null => 'gray',
            CertificatePdfStatus::Pending => 'warning',
            CertificatePdfStatus::Generated => 'success',
            CertificatePdfStatus::Failed => 'danger',
        };
    }

    private function needsRegeneration(Model $record): bool
    {
        $certificate = $this->certificateFor($record);
        if ($certificate === null) {
            return false;
        }

        if ($certificate->pdf_status === CertificatePdfStatus::Failed) {
            return true;
        }

        $template = $this->activity()->certificateTemplate;

        return $template instanceof CertificateTemplate
            && (int) $certificate->template_version !== (int) $template->version;
    }

    /**
     * @return list<int>
     */
    private function registrationIdsForEligibility(string $status): array
    {
        $registrations = $this->activity()->registrations()
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->get();
        $results = app(CertificateEligibilityService::class)->evaluateMany($registrations);

        return $registrations
            ->filter(fn (Model $registration): bool => ($results->get($registration->getKey())?->status->value) === $status)
            ->map(fn (Model $registration): int => (int) $registration->getKey())
            ->values()
            ->all() ?: [-1];
    }

    private function applyCertificateStateFilter(Builder $query, string $state): void
    {
        $program = $this->activity();
        $type = $program->getMorphClass();
        $userColumn = $query->getModel()->getTable().'.user_id';

        if ($state === 'missing') {
            $query->whereNotExists(function ($sub) use ($program, $type, $userColumn): void {
                $sub->selectRaw('1')
                    ->from('certificates')
                    ->whereColumn('certificates.user_id', $userColumn)
                    ->where('certificates.certificateable_type', $type)
                    ->where('certificates.certificateable_id', $program->getKey())
                    ->whereNull('certificates.revoked_at');
            });

            return;
        }

        $query->whereExists(function ($sub) use ($program, $type, $state, $userColumn): void {
            $sub->selectRaw('1')
                ->from('certificates')
                ->whereColumn('certificates.user_id', $userColumn)
                ->where('certificates.certificateable_type', $type)
                ->where('certificates.certificateable_id', $program->getKey())
                ->whereNull('certificates.revoked_at')
                ->where('certificates.pdf_status', $state);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function bannerCounts(): array
    {
        $registrations = $this->activity()->registrations()
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->get();
        $results = app(CertificateEligibilityService::class)->evaluateMany($registrations);
        $issued = Certificate::query()
            ->active()
            ->where('certificateable_type', $this->activity()->getMorphClass())
            ->where('certificateable_id', $this->activity()->getKey())
            ->where('pdf_status', CertificatePdfStatus::Generated)
            ->count();

        return [
            'eligible' => $results->filter(fn (EligibilityResult $result): bool => $result->eligible)->count(),
            'issued' => $issued,
            'awaiting' => $results->filter(fn (EligibilityResult $result): bool => $result->status === CertificateEligibilityStatus::AwaitingData)->count(),
        ];
    }

    /**
     * @return Collection<int, array<int, string|null>>
     */
    private function rosterRows(): Collection
    {
        $registrations = $this->activity()->registrations()
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->with('user')
            ->get();
        $this->hydratePage($registrations);

        return $registrations->map(function (Model $registration): array {
            $certificate = $this->certificateFor($registration);
            $result = $this->resultFor($registration);

            return [
                $registration->user?->certificateName() ?? $registration->user?->name,
                $this->metricLabels[(int) $registration->getKey()] ?? $this->attendanceLabel($registration),
                $registration->getAttribute('score') !== null && $registration->getAttribute('score') !== ''
                    ? (string) $registration->getAttribute('score')
                    : '—',
                $this->eligibilityLabel($registration).($result->reasons !== [] ? ' — '.implode(' — ', $result->reasons) : ''),
                $certificate?->certificate_number ?? '—',
                $certificate ? route('certificates.verify', $certificate->verification_code) : '—',
            ];
        });
    }

    protected function activity(): Model
    {
        return $this->getOwnerRecord();
    }

    /**
     * @return list<TextColumn>
     */
    protected function metricColumns(): array
    {
        return [
            TextColumn::make('attendance_percentage')
                ->label('نسبة الحضور')
                ->state(fn (Model $record): string => $this->attendanceLabel($record)),
            TextColumn::make('score')
                ->label('الدرجة')
                ->formatStateUsing(fn ($state): string => $state === null || $state === '' ? '—' : (string) $state),
        ];
    }

    protected function metricLabel(Model $record): string
    {
        return $this->metricLabels[(int) $record->getKey()] ?? '—';
    }

    private function templateIsReady(?CertificateTemplate $template = null): bool
    {
        $template ??= $this->activity()->certificateTemplate;

        return $template instanceof CertificateTemplate
            && $template->status === CertificateTemplateStatus::Ready;
    }

    /**
     * @return Collection<int, Model>
     */
    private function unwrap(Collection|Paginator|CursorPaginator $records): Collection
    {
        if ($records instanceof Paginator || $records instanceof CursorPaginator) {
            /** @var Collection<int, Model> $collection */
            $collection = method_exists($records, 'getCollection') ? $records->getCollection() : collect();

            return $collection;
        }

        return $records;
    }
}
