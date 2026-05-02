<?php

namespace App\Filament\Resources\Concerns;

use App\Models\LearningPath;
use App\Models\TrainingProgram;
use App\Models\User;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Shared behavior for TrainingProgram / LearningPath editor pivot relation managers.
 *
 * @property-read TrainingProgram|LearningPath $ownerRecord
 */
trait ManagesFilamentTrainingEditors
{
    protected function getAttachAuthorizationResponse(): Response
    {
        $user = auth()->user();
        if ($user === null || ! $user->can('manageEditors', $this->getOwnerRecord())) {
            return Response::deny();
        }

        return Response::allow();
    }

    protected function getDetachAuthorizationResponse(Model $record): Response
    {
        $user = auth()->user();
        if ($user === null || ! $user->can('manageEditors', $this->getOwnerRecord())) {
            return Response::deny();
        }

        if (! $record instanceof User) {
            return Response::deny();
        }

        $owner = $this->getOwnerRecord();

        if ((int) $record->id === (int) $owner->created_by) {
            return Response::deny('لا يمكن إزالة المنشئ من صلاحية التحرير (يظل مفعّلاً تلقائياً).');
        }

        if ((int) $record->id === (int) $owner->owner_id) {
            return Response::deny('لا يمكن إزالة المالك من قائمة المحررين.');
        }

        return Response::allow();
    }

    protected function getViewAuthorizationResponse(Model $record): Response
    {
        $user = auth()->user();
        if ($user === null) {
            return Response::deny();
        }

        return $user->can('view', $this->getOwnerRecord())
            ? Response::allow()
            : Response::deny();
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('view', $ownerRecord);
    }

    protected function editorRowLabel(User $record): string
    {
        $owner = $this->getOwnerRecord();
        $bits = [];
        if ((int) $record->id === (int) $owner->owner_id) {
            $bits[] = 'مالك';
        }
        if ((int) $record->id === (int) $owner->created_by) {
            $bits[] = 'منشئ';
        }

        return $bits !== [] ? implode(' · ', $bits) : 'محرر';
    }

    protected function modifyAttachableUsersQuery(Builder $query): Builder
    {
        $owner = $this->getOwnerRecord();

        $query->where('is_active', true);

        if ($owner->created_by !== null) {
            $query->where('id', '!=', $owner->created_by);
        }
        if ($owner->owner_id !== null) {
            $query->where('id', '!=', $owner->owner_id);
        }

        return $query;
    }

    protected function configureEditorsTable(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('البريد')
                    ->searchable(),
                TextColumn::make('role_notes')
                    ->label('الدور في الفريق')
                    ->getStateUsing(fn (User $record): string => $this->editorRowLabel($record)),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('إضافة محرر')
                    ->icon('heroicon-o-user-plus')
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $this->modifyAttachableUsersQuery($query))
                    ->beforeFormValidated(fn () => Gate::authorize('manageEditors', $this->getOwnerRecord())),
            ])
            ->actions([
                DetachAction::make()
                    ->label('إزالة')
                    ->before(fn () => Gate::authorize('manageEditors', $this->getOwnerRecord()))
                    ->visible(function (User $record): bool {
                        $user = auth()->user();
                        if ($user === null || ! $user->can('manageEditors', $this->getOwnerRecord())) {
                            return false;
                        }

                        $owner = $this->getOwnerRecord();

                        if ((int) $record->id === (int) $owner->created_by) {
                            return false;
                        }

                        if ((int) $record->id === (int) $owner->owner_id) {
                            return false;
                        }

                        return true;
                    }),
            ]);
    }
}
