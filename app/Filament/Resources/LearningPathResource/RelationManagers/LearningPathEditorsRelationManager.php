<?php

namespace App\Filament\Resources\LearningPathResource\RelationManagers;

use App\Filament\Resources\Concerns\ManagesFilamentTrainingEditors;
use App\Models\LearningPath;
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

class LearningPathEditorsRelationManager extends RelationManager
{
    use ManagesFilamentTrainingEditors;

    protected static string $relationship = 'editors';

    protected static ?string $title = 'فريق العمل والصلاحيات';

    protected function makeTable(): Table
    {
        $table = Table::make($this)
            ->query(fn (): Builder => $this->learningPathTeamQuery())
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
     * المنشئ، المسؤول، المحررون (بدون تكرار).
     */
    protected function learningPathTeamQuery(): Builder
    {
        /** @var LearningPath $path */
        $path = $this->getOwnerRecord();
        $path->loadMissing(['editors']);

        $ids = collect();
        if ($path->created_by !== null) {
            $ids->push($path->created_by);
        }
        if ($path->owner_id !== null) {
            $ids->push($path->owner_id);
        }
        $ids = $ids->merge($path->editors->pluck('id'))
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
                    ->getStateUsing(fn (User $record): string => FilamentTrainingAccess::pathTeamMemberRoleLabels($record, $this->getLearningPath())),
                TextColumn::make('team_permission')
                    ->label('الصلاحية')
                    ->getStateUsing(fn (User $record): string => FilamentTrainingAccess::pathTeamMemberPermissionLabel($record, $this->getLearningPath())),
                TextColumn::make('attached_at')
                    ->label('تاريخ الإضافة')
                    ->getStateUsing(fn (User $record): string => FilamentTrainingAccess::pathTeamMemberAttachedAtLabel($record, $this->getLearningPath())),
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
                        $path = $this->getLearningPath();
                        if (! auth()->user()?->can('manageEditors', $path)) {
                            return false;
                        }

                        if ((int) $record->id === (int) $path->created_by) {
                            return false;
                        }

                        if ((int) $record->id === (int) $path->owner_id) {
                            return false;
                        }

                        return $path->editors()->whereKey($record->id)->exists();
                    })
                    ->action(function (User $record): void {
                        $path = $this->getLearningPath();
                        Gate::authorize('manageEditors', $path);
                        if ((int) $record->id === (int) $path->created_by || (int) $record->id === (int) $path->owner_id) {
                            return;
                        }
                        if (! $path->editors()->whereKey($record->id)->exists()) {
                            return;
                        }
                        $path->editors()->detach($record->id);
                    }),
            ]);
    }

    protected function getLearningPath(): LearningPath
    {
        $path = $this->getOwnerRecord();
        assert($path instanceof LearningPath);

        return $path;
    }
}
