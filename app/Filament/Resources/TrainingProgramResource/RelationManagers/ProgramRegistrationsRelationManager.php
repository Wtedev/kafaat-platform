<?php

namespace App\Filament\Resources\TrainingProgramResource\RelationManagers;

use App\Enums\RegistrationStatus;
use App\Exceptions\ProgramCapacityExceededException;
use App\Filament\Actions\ExportProgramRegistrantsAction;
use App\Filament\Support\ProgramRegistrationsTableFilters;
use App\Filament\Support\RegistrationFilamentTableSupport;
use App\Jobs\BulkApproveProgramRegistrationsJob;
use App\Jobs\BulkRejectProgramRegistrationsJob;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Exports\ProgramRegistrationExportAuthorization;
use App\Services\Exports\ProgramRegistrationExportService;
use App\Services\ProgramRegistration\BulkProgramRegistrationProcessor;
use App\Services\ProgramRegistrationService;
use App\Support\Exports\ProgramRegistrationExportColumns;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProgramRegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'المسجلين في البرنامج';

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
            ->description(fn (): string => 'عدد النتائج: '.en_num($this->getAllTableRecordsCount()))
            ->columns([
                RegistrationFilamentTableSupport::beneficiaryNameColumn(),
                RegistrationFilamentTableSupport::acceptanceStatusColumn(),
            ])
            ->filters(ProgramRegistrationsTableFilters::make())
            ->deferFilters(false)
            ->headerActions([
                ExportProgramRegistrantsAction::make(
                    fn (): TrainingProgram => $this->getOwnerRecord(),
                    $this,
                ),
            ])
            ->selectable()
            ->selectCurrentPageOnly(false)
            ->actions([
                Action::make('approve')
                    ->label('قبول')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد قبول الطلب')
                    ->modalDescription('هل تريد قبول طلب التسجيل في هذا البرنامج؟')
                    ->modalSubmitActionLabel('نعم، قبول')
                    ->visible(fn (ProgramRegistration $record): bool => $record->status === RegistrationStatus::Pending)
                    ->authorize('approve')
                    ->action(function (ProgramRegistration $record): void {
                        try {
                            app(ProgramRegistrationService::class)->approve($record, auth()->user());
                            Notification::make()->title('تمت الموافقة على التسجيل')->success()->send();
                        } catch (ProgramCapacityExceededException) {
                            Notification::make()->title('البرنامج بلغ طاقته القصوى')->danger()->send();
                        }
                    }),

                Action::make('reject')
                    ->label('رفض')
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
                    ->authorize('reject')
                    ->action(function (ProgramRegistration $record, array $data): void {
                        app(ProgramRegistrationService::class)->reject($record, $data['rejected_reason'] ?? null);
                        Notification::make()->title('تم رفض التسجيل')->warning()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    $this->bulkApproveAction(),
                    $this->bulkRejectAction(),
                    $this->bulkExportAction(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private function bulkApproveAction(): BulkAction
    {
        return BulkAction::make('bulkApprove')
            ->label('قبول المحدد')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('قبول التسجيلات المحددة')
            ->modalDescription(function (Collection $records): string {
                $pending = $records->where('status', RegistrationStatus::Pending)->count();

                return 'سيتم محاولة قبول '.$pending.' طلباً معلّقاً من أصل '.$records->count()
                    .' محدداً. يُتجاوز المقبولون مسبقاً، ويُحترم حد السعة.';
            })
            ->modalSubmitActionLabel('تأكيد القبول')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $actor = Auth::user();
                $program = $this->getOwnerRecord();
                if (! $actor instanceof User || ! $program instanceof TrainingProgram) {
                    return;
                }

                $filters = $this->tableFilters ?? [];
                $ids = $records->modelKeys();

                if (count($ids) > BulkProgramRegistrationProcessor::QUEUE_THRESHOLD) {
                    BulkApproveProgramRegistrationsJob::dispatch(
                        $actor->id,
                        $program->id,
                        array_map('intval', $ids),
                        is_array($filters) ? $filters : [],
                    );
                    Notification::make()
                        ->title('أُرسلت مهمة القبول الجماعي للطابور')
                        ->body('ستصلك إشعاراً عند الانتهاء (أكثر من '.BulkProgramRegistrationProcessor::QUEUE_THRESHOLD.' سجلاً).')
                        ->success()
                        ->send();

                    return;
                }

                $summary = app(BulkProgramRegistrationProcessor::class)->approve(
                    $records,
                    $actor,
                    $program,
                    is_array($filters) ? $filters : [],
                );

                Notification::make()
                    ->title('اكتمل القبول الجماعي')
                    ->body('قُبل '.$summary['approved'].'، وتُخطّي '.$summary['skipped']
                        .($summary['capacity_blocked'] > 0
                            ? '، وتوقف '.$summary['capacity_blocked'].' بسبب السعة.'
                            : '.'))
                    ->success()
                    ->send();
            });
    }

    private function bulkRejectAction(): BulkAction
    {
        return BulkAction::make('bulkReject')
            ->label('رفض المحدد')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('رفض التسجيلات المحددة')
            ->modalDescription(fn (Collection $records): string => 'سيتم رفض الطلبات المعلّقة ضمن '.$records->count().' محدداً.')
            ->modalSubmitActionLabel('تأكيد الرفض')
            ->form([
                Textarea::make('rejected_reason')
                    ->label('سبب الرفض (اختياري — يُطبَّق على الجميع)')
                    ->rows(3),
            ])
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                $actor = Auth::user();
                $program = $this->getOwnerRecord();
                if (! $actor instanceof User || ! $program instanceof TrainingProgram) {
                    return;
                }

                $filters = $this->tableFilters ?? [];
                $reason = isset($data['rejected_reason']) ? (string) $data['rejected_reason'] : null;
                $ids = $records->modelKeys();

                if (count($ids) > BulkProgramRegistrationProcessor::QUEUE_THRESHOLD) {
                    BulkRejectProgramRegistrationsJob::dispatch(
                        $actor->id,
                        $program->id,
                        array_map('intval', $ids),
                        $reason,
                        is_array($filters) ? $filters : [],
                    );
                    Notification::make()
                        ->title('أُرسلت مهمة الرفض الجماعي للطابور')
                        ->body('ستصلك إشعاراً عند الانتهاء.')
                        ->success()
                        ->send();

                    return;
                }

                $summary = app(BulkProgramRegistrationProcessor::class)->reject(
                    $records,
                    $actor,
                    $program,
                    $reason,
                    is_array($filters) ? $filters : [],
                );

                Notification::make()
                    ->title('اكتمل الرفض الجماعي')
                    ->body('رُفض '.$summary['rejected'].'، وتُخطّي '.$summary['skipped'].'.')
                    ->warning()
                    ->send();
            });
    }

    private function bulkExportAction(): BulkAction
    {
        return BulkAction::make('bulkExport')
            ->label('تصدير المحدد')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('تصدير الصفوف المحددة')
            ->modalDescription(fn (Collection $records): string => 'سيتم تصدير '.$records->count().' صفاً محدداً بالأعمدة الافتراضية.')
            ->modalSubmitActionLabel('تصدير')
            ->action(function (Collection $records): ?BinaryFileResponse {
                $actor = Auth::user();
                $program = $this->getOwnerRecord();
                if (! $actor instanceof User || ! $program instanceof TrainingProgram) {
                    return null;
                }

                abort_unless(ProgramRegistrationExportAuthorization::canExport($actor, $program), 403);

                $keys = ProgramRegistrationExportAuthorization::defaultColumnKeysFor($actor);
                $keys = ProgramRegistrationExportAuthorization::filterAllowedColumnKeys($actor, $keys);
                if ($keys === []) {
                    $keys = array_keys(ProgramRegistrationExportColumns::optionLabels($actor));
                }

                $selectedIds = array_map('intval', $records->modelKeys());
                $filters = $this->tableFilters ?? [];
                $service = app(ProgramRegistrationExportService::class);

                app(BulkProgramRegistrationProcessor::class)->auditExport(
                    $actor,
                    $program,
                    count($selectedIds),
                    is_array($filters) ? $filters : [],
                );

                $download = $service->download(
                    $actor,
                    $program,
                    $keys,
                    ProgramRegistrationExportService::SCOPE_SELECTED,
                    null,
                    $selectedIds,
                );

                if ($download === null) {
                    Notification::make()->title('لا توجد صفوف للتصدير')->warning()->send();

                    return null;
                }

                Notification::make()->title('تم تجهيز ملف التصدير')->success()->send();

                return $download;
            });
    }
}
