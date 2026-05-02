<?php

namespace App\Filament\Resources\TrainingProgramResource\RelationManagers;

use App\Filament\Resources\Concerns\ManagesFilamentTrainingEditors;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Support\FilamentTrainingAccess;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TrainingProgramEditorsRelationManager extends RelationManager
{
    use ManagesFilamentTrainingEditors;

    protected static string $relationship = 'editors';

    protected static ?string $title = 'فريق العمل والصلاحيات';

    protected function makeTable(): Table
    {
        $table = Table::make($this)
            ->query(fn (): Builder => $this->trainingProgramTeamQuery())
            ->modifyQueryUsing($this->modifyQueryWithActiveTab(...))
            ->queryStringIdentifier(Str::lcfirst(class_basename(static::class)))
            ->recordAction(function (Model $record, Table $table): ?string {
                foreach (['view', 'edit'] as $action) {
                    $action = $table->getAction($action);

                    if (! $action) {
                        continue;
                    }

                    $action->record($record);

                    $actionGroup = $action->getGroup();

                    while ($actionGroup) {
                        $actionGroup->record($record);

                        $actionGroup = $actionGroup->getGroup();
                    }

                    if ($action->isHidden()) {
                        continue;
                    }

                    if ($action->getUrl()) {
                        continue;
                    }

                    return $action->getName();
                }

                return null;
            });

        if (! $table->hasCustomRecordUrl()) {
            $table->recordUrl(function (Model $record, Table $table): ?string {
                foreach (['view', 'edit'] as $action) {
                    $action = $table->getAction($action);

                    if (! $action) {
                        continue;
                    }

                    $action->record($record);

                    $actionGroup = $action->getGroup();

                    while ($actionGroup) {
                        $actionGroup->record($record);

                        $actionGroup = $actionGroup->getGroup();
                    }

                    if ($action->isHidden()) {
                        continue;
                    }

                    $url = $action->getUrl();

                    if (! $url) {
                        continue;
                    }

                    return $url;
                }

                return null;
            });
        }

        $table->authorizeReorder(fn (): bool => $this->canReorder());

        return $table
            ->when(static::getInverseRelationshipName(), fn (Table $table, ?string $inverseRelationshipName): Table => $table->inverseRelationship($inverseRelationshipName))
            ->when(static::getModelLabel(), fn (Table $table, string $modelLabel): Table => $table->modelLabel($modelLabel))
            ->when(static::getPluralModelLabel(), fn (Table $table, string $pluralModelLabel): Table => $table->pluralModelLabel($pluralModelLabel))
            ->when(static::getRecordTitleAttribute(), fn (Table $table, string $recordTitleAttribute): Table => $table->recordTitleAttribute($recordTitleAttribute))
            ->heading($this->getTableHeading() ?? static::getTitle($this->getOwnerRecord(), $this->getPageClass()));
    }

    /**
     * All distinct users: المنشئ، المسؤول، المحررون (deduplicated).
     */
    protected function trainingProgramTeamQuery(): Builder
    {
        /** @var TrainingProgram $program */
        $program = $this->getOwnerRecord();
        $program->loadMissing(['editors']);

        $ids = collect();
        if ($program->created_by !== null) {
            $ids->push($program->created_by);
        }
        if ($program->owner_id !== null) {
            $ids->push($program->owner_id);
        }
        $ids = $ids->merge($program->editors->pluck('id'))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return User::query()->whereRaw('0 = 1');
        }

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->emptyStateHeading('لا يوجد محررون إضافيون بعد')
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('team_roles')
                    ->label('الدور')
                    ->getStateUsing(fn (User $record): string => FilamentTrainingAccess::teamMemberRoleLabels($record, $this->getTrainingProgram())),
                TextColumn::make('team_permission')
                    ->label('الصلاحية')
                    ->getStateUsing(fn (User $record): string => FilamentTrainingAccess::teamMemberPermissionLabel($record, $this->getTrainingProgram())),
                TextColumn::make('attached_at')
                    ->label('تاريخ الإضافة')
                    ->getStateUsing(fn (User $record): string => FilamentTrainingAccess::teamMemberAttachedAtLabel($record, $this->getTrainingProgram())),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('إضافة محرر')
                    ->icon('heroicon-o-user-plus')
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $this->modifyAttachableUsersQuery($query))
                    ->beforeFormValidated(fn () => Gate::authorize('manageEditors', $this->getOwnerRecord()))
                    ->visible(fn (): bool => auth()->user()?->can('manageEditors', $this->getOwnerRecord()) ?? false),
            ])
            ->actions([
                Action::make('removeEditor')
                    ->label('إزالة من المحررين')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('إزالة المحرر')
                    ->modalDescription('سيتم إزالة هذا المستخدم من قائمة المحررين فقط. المنشئ والمسؤول لا يُزالان من هنا.')
                    ->visible(function (User $record): bool {
                        $program = $this->getTrainingProgram();
                        if (! auth()->user()?->can('manageEditors', $program)) {
                            return false;
                        }

                        if ((int) $record->id === (int) $program->created_by) {
                            return false;
                        }

                        if ((int) $record->id === (int) $program->owner_id) {
                            return false;
                        }

                        return $program->editors()->whereKey($record->id)->exists();
                    })
                    ->action(function (User $record): void {
                        $program = $this->getTrainingProgram();
                        Gate::authorize('manageEditors', $program);
                        if ((int) $record->id === (int) $program->created_by || (int) $record->id === (int) $program->owner_id) {
                            return;
                        }
                        if (! $program->editors()->whereKey($record->id)->exists()) {
                            return;
                        }
                        $program->editors()->detach($record->id);
                    }),
            ]);
    }

    protected function getTrainingProgram(): TrainingProgram
    {
        $program = $this->getOwnerRecord();
        assert($program instanceof TrainingProgram);

        return $program;
    }
}
