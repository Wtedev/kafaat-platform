<?php

namespace App\Filament\Resources\VolunteerTeamResource\RelationManagers;

use App\Models\TeamMember;
use App\Models\VolunteerTeam;
use App\Support\FilamentAssignmentVisibility;
use App\Support\StaffFilamentRoles;
use App\Support\UserDirectoryTabs;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TeamMembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'أعضاء الفريق';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return self::actorMayManageMembership(auth()->user());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->relationship(
                    'user',
                    'name',
                    modifyQueryUsing: function (Builder $query): void {
                        /** @var VolunteerTeam $team */
                        $team = $this->getOwnerRecord();
                        $teamId = $team->getKey();

                        $query->where('is_active', true)
                            ->where(function (Builder $q): void {
                                UserDirectoryTabs::applyTabScope($q, UserDirectoryTabs::TAB_TRAINEES);
                                $q->orWhere(function (Builder $inner): void {
                                    UserDirectoryTabs::applyTabScope($inner, UserDirectoryTabs::TAB_VOLUNTEERS);
                                });
                            })
                            ->whereDoesntHave('teamMemberships', fn (Builder $q): Builder => $q->where('volunteer_team_id', $teamId));
                    },
                )
                ->searchable()
                ->preload()
                ->required()
                ->label('المستفيدة')
                ->helperText('اختر متدربة لإضافتها للفريق — يُحدَّث دورها إلى متطوعة تلقائياً.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')
                    ->label('البريد')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإضافة')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => self::actorMayManageMembership(auth()->user()))
                    ->after(function (TeamMember $record): void {
                        $user = $record->user;
                        if ($user === null) {
                            return;
                        }

                        if (! $user->hasRole('volunteer')) {
                            $user->syncRoles(['volunteer']);
                        }

                        if (! in_array($user->role_type, ['beneficiary', 'trainee', 'volunteer'], true)) {
                            $user->update(['role_type' => 'beneficiary']);
                        }
                    }),
            ])
            ->actions([
                DeleteAction::make()
                    ->visible(fn (): bool => self::actorMayManageMembership(auth()->user())),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => self::actorMayManageMembership(auth()->user())),
                ]),
            ]);
    }

    private static function actorMayManageMembership(?\App\Models\User $actor): bool
    {
        if ($actor === null) {
            return false;
        }

        if (FilamentAssignmentVisibility::bypasses($actor)) {
            return true;
        }

        return StaffFilamentRoles::isProgramsActivitiesManager($actor)
            || $actor->hasAnyRole(StaffFilamentRoles::VOLUNTEERING_COORDINATOR);
    }
}
