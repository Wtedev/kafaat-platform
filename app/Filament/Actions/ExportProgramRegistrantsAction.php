<?php

namespace App\Filament\Actions;

use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Exports\ProgramRegistrationExportAuthorization;
use App\Services\Exports\ProgramRegistrationExportService;
use App\Support\Exports\ProgramRegistrationExportColumns;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Shared Filament action: «تصدير المسجلين» for program view / registrations relation manager.
 */
final class ExportProgramRegistrantsAction
{
    public static function make(
        TrainingProgram|callable $program,
        ?RelationManager $relationManager = null,
    ): Action {
        return Action::make('exportProgramRegistrants')
            ->label('تصدير المسجلين')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(function () use ($program): bool {
                $resolved = self::resolveProgram($program);
                $actor = auth()->user();

                return $resolved instanceof TrainingProgram
                    && $actor instanceof User
                    && ProgramRegistrationExportAuthorization::canExport($actor, $resolved);
            })
            ->modalHeading('تصدير المسجلين إلى Excel')
            ->modalDescription(
                'اختر الأعمدة ونطاق التصدير. التصدير يقتصر على مسجّلي هذا البرنامج فقط.'
                .' عند اختيار «وفق فلاتر الجدول الحالي» تُحترم فلاتر جدول المسجلين إن وُجدت.'
            )
            ->modalSubmitActionLabel('تصدير')
            ->form(function () use ($program, $relationManager): array {
                $actor = auth()->user();
                $actorUser = $actor instanceof User ? $actor : null;
                $hasRelationTable = $relationManager !== null;

                $groups = ProgramRegistrationExportColumns::groupedOptionLabels($actorUser);
                $titles = ProgramRegistrationExportColumns::groupTitles();
                $defaults = ProgramRegistrationExportAuthorization::defaultColumnKeysFor(
                    $actorUser ?? new User,
                );

                $columnFields = [];
                foreach ($groups as $groupKey => $options) {
                    $fieldName = 'columns_'.$groupKey;
                    $groupDefaults = array_values(array_intersect($defaults, array_keys($options)));
                    $columnFields[] = Fieldset::make($titles[$groupKey] ?? $groupKey)
                        ->schema([
                            CheckboxList::make($fieldName)
                                ->hiddenLabel()
                                ->options($options)
                                ->default($groupDefaults)
                                ->bulkToggleable()
                                ->columns(2)
                                ->live(),
                        ]);
                }

                return [
                    Select::make('scope')
                        ->label('نطاق التصدير')
                        ->options(ProgramRegistrationExportService::scopeOptions(
                            includeTableFilters: $hasRelationTable,
                            includeSelected: $hasRelationTable,
                        ))
                        ->default(
                            $hasRelationTable
                                ? ProgramRegistrationExportService::SCOPE_TABLE_FILTERS
                                : ProgramRegistrationExportService::SCOPE_ALL
                        )
                        ->required()
                        ->live()
                        ->helperText(
                            $hasRelationTable
                                ? 'الافتراضي يحترم فلاتر جدول المسجلين الحالي. يمكنك التبديل إلى حالة محددة أو الصفوف المحددة.'
                                : 'يُصدَّر حسب الحالة المختارة لجميع مسجّلي البرنامج.'
                        ),
                    ...$columnFields,
                    Placeholder::make('export_summary')
                        ->label('ملخص')
                        ->content(function (Get $get) use ($program, $relationManager, $actorUser): string {
                            $resolved = self::resolveProgram($program);
                            if (! $resolved instanceof TrainingProgram || ! $actorUser instanceof User) {
                                return '—';
                            }

                            $keys = self::collectColumnKeys($get, $actorUser);
                            $scope = (string) ($get('scope') ?: ProgramRegistrationExportService::SCOPE_ALL);
                            $service = app(ProgramRegistrationExportService::class);

                            [$filteredQuery, $selectedIds] = self::tableContext($relationManager, $scope);

                            $count = $service->count($resolved, $scope, $filteredQuery, $selectedIds);
                            $cols = count($keys);

                            if ($cols < 1) {
                                return 'اختر عموداً واحداً على الأقل.';
                            }

                            if ($count < 1) {
                                return 'لا توجد تسجيلات مطابقة للتصدير.';
                            }

                            return "سيتم تصدير {$count} مسجلًا باستخدام {$cols} أعمدة.";
                        }),
                ];
            })
            ->action(function (array $data) use ($program, $relationManager) {
                $resolved = self::resolveProgram($program);
                $actor = auth()->user();

                if (! $resolved instanceof TrainingProgram || ! $actor instanceof User) {
                    abort(403);
                }

                abort_unless(
                    ProgramRegistrationExportAuthorization::canExport($actor, $resolved),
                    403,
                );

                $keys = [];
                foreach (array_keys(ProgramRegistrationExportColumns::groupTitles()) as $groupKey) {
                    $part = $data['columns_'.$groupKey] ?? [];
                    if (is_array($part)) {
                        $keys = [...$keys, ...$part];
                    }
                }
                $keys = ProgramRegistrationExportAuthorization::filterAllowedColumnKeys($actor, $keys);

                if ($keys === []) {
                    Notification::make()
                        ->title('اختر عموداً واحداً على الأقل')
                        ->danger()
                        ->send();

                    return null;
                }

                $scope = (string) ($data['scope'] ?? ProgramRegistrationExportService::SCOPE_ALL);
                [$filteredQuery, $selectedIds] = self::tableContext($relationManager, $scope);

                $service = app(ProgramRegistrationExportService::class);
                $download = $service->download(
                    $actor,
                    $resolved,
                    $keys,
                    $scope,
                    $filteredQuery,
                    $selectedIds,
                );

                if ($download === null) {
                    Notification::make()
                        ->title('لا توجد تسجيلات مطابقة للتصدير.')
                        ->warning()
                        ->send();

                    return null;
                }

                return $download;
            });
    }

    private static function resolveProgram(TrainingProgram|callable $program): ?TrainingProgram
    {
        $resolved = is_callable($program) ? $program() : $program;

        return $resolved instanceof TrainingProgram ? $resolved : null;
    }

    /**
     * @return array{0: Builder|null, 1: list<int|string>|null}
     */
    private static function tableContext(?RelationManager $relationManager, string $scope): array
    {
        if ($relationManager === null) {
            return [null, null];
        }

        $filteredQuery = null;
        $selectedIds = null;

        if ($scope === ProgramRegistrationExportService::SCOPE_TABLE_FILTERS) {
            if (method_exists($relationManager, 'getFilteredTableQuery')) {
                /** @var Builder $filteredQuery */
                $filteredQuery = $relationManager->getFilteredTableQuery();
            }
        }

        if ($scope === ProgramRegistrationExportService::SCOPE_SELECTED) {
            if (method_exists($relationManager, 'getSelectedTableRecords')) {
                /** @var Collection<int, Model> $selected */
                $selected = $relationManager->getSelectedTableRecords();
                $selectedIds = $selected->modelKeys();
            }
        }

        return [$filteredQuery, $selectedIds];
    }

    /**
     * @return list<string>
     */
    private static function collectColumnKeys(Get $get, User $actor): array
    {
        $keys = [];
        foreach (array_keys(ProgramRegistrationExportColumns::groupTitles()) as $groupKey) {
            $part = $get('columns_'.$groupKey) ?? [];
            if (is_array($part)) {
                $keys = [...$keys, ...$part];
            }
        }

        return ProgramRegistrationExportAuthorization::filterAllowedColumnKeys($actor, $keys);
    }
}
