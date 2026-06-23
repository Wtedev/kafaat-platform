<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\Pages\BaseListRecords;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\VolunteerTeamResource;
use App\Models\VolunteerTeam;
use App\Support\UserDirectoryTabs;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends BaseListRecords
{
    protected static string $resource = UserResource::class;

    public function getDefaultActiveTab(): string|int|null
    {
        $keys = array_keys($this->getTabs());

        return $keys[0] ?? null;
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $actor = auth()->user();
        $tabs = [];

        foreach (UserDirectoryTabs::tabDefinitions() as $key => $definition) {
            if (! UserDirectoryTabs::actorCanViewTab($actor, $key)) {
                continue;
            }

            $tabs[$key] = Tab::make($definition['label'])
                ->modifyQueryUsing(fn (Builder $query): Builder => UserDirectoryTabs::applyTabScope($query, $key));
        }

        return $tabs;
    }

    protected function getListPageToolbarActions(): array
    {
        $actions = [];

        if (UserResource::canCreate()) {
            $actions[] = CreateAction::make()
                ->url(function (): string {
                    $params = [];
                    if (filled($this->activeTab) && UserDirectoryTabs::isValidTab((string) $this->activeTab)) {
                        $params['directory_tab'] = $this->activeTab;
                    }

                    return UserResource::getUrl('create', $params);
                });
        }

        if ($this->activeTab === UserDirectoryTabs::TAB_VOLUNTEERS
            && auth()->user()?->can('volunteering.view')) {
            $team = VolunteerTeam::canonical();
            if ($team !== null) {
                $actions[] = Action::make('manage_volunteer_team')
                    ->label('إدارة عضوية الفريق')
                    ->icon('heroicon-o-user-group')
                    ->color('gray')
                    ->url(VolunteerTeamResource::getUrl('view', ['record' => $team]));
            }
        }

        return $actions;
    }
}
