<?php

namespace App\Filament\Resources\ProfileResource\Pages;

use App\Exports\BeneficiaryProfilesExport;
use App\Filament\Resources\Pages\BaseListRecords;
use App\Filament\Resources\ProfileResource;
use App\Services\Audit\AuditLogger;
use App\Services\Exports\BeneficiaryExportAuthorization;
use App\Support\Exports\BeneficiaryProfileExportColumns;
use App\Enums\AuditLogResult;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;

class ListProfiles extends BaseListRecords
{
    protected static string $resource = ProfileResource::class;

    protected function getListPageToolbarActions(): array
    {
        return [
            $this->exportBeneficiaryProfilesAction(),
            CreateAction::make()
                ->visible(fn (): bool => (bool) auth()->user()?->can('roles.view')),
        ];
    }

    protected function exportBeneficiaryProfilesAction(): Action
    {
        return Action::make('exportBeneficiaryProfiles')
            ->label('تصدير Excel')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->can('export', \App\Models\Profile::class) ?? false)
            ->modalHeading('تصدير ملفات المستفيدين')
            ->modalDescription('يُصدَّر المستفيدون فقط (مستفيد / متدرب / متطوع). تُطبَّق فلاتر وبحث الجدول الحالي على النتائج.')
            ->modalSubmitActionLabel('تصدير')
            ->form([
                CheckboxList::make('columns')
                    ->label('الأعمدة المطلوبة')
                    ->options(BeneficiaryProfileExportColumns::optionLabels())
                    ->default(BeneficiaryProfileExportColumns::defaultKeys())
                    ->columns(2)
                    ->bulkToggleable()
                    ->required()
                    ->minItems(1),
            ])
            ->action(function (array $data): mixed {
                $allowed = array_keys(BeneficiaryProfileExportColumns::optionLabels());
                $keys = BeneficiaryExportAuthorization::filterAllowedColumnKeys(
                    auth()->user(),
                    array_values(array_intersect($data['columns'] ?? [], $allowed)),
                );

                if ($keys === []) {
                    Notification::make()
                        ->title('اختر عموداً واحداً على الأقل')
                        ->danger()
                        ->send();

                    return null;
                }

                $profiles = $this->getTableQueryForExport()
                    ->forPortalBeneficiaries()
                    ->with(['user'])
                    ->get();

                if ($profiles->isEmpty()) {
                    Notification::make()
                        ->title('لا توجد ملفات مستفيدين للتصدير')
                        ->warning()
                        ->send();

                    return null;
                }

                $filename = 'beneficiary-profiles-'.now()->format('Y-m-d-His').'.xlsx';

                app(AuditLogger::class)->record(
                    auth()->user(),
                    'export.generated',
                    AuditLogResult::Success,
                    metadata: [
                        'export_type' => 'beneficiary_profiles',
                        'row_count' => $profiles->count(),
                        'selected_columns' => $keys,
                    ],
                    request: request(),
                );

                return Excel::download(
                    new BeneficiaryProfilesExport($profiles, $keys),
                    $filename,
                );
            });
    }
}
