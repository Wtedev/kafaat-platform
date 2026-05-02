<?php

namespace App\Filament\Resources\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord as FilamentViewRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Group;

/**
 * يعرض إجراءات صفحة العرض (تعديل، حذف، …) أسفل المحتوى بدل هيدر الصفحة.
 */
abstract class BaseViewRecord extends FilamentViewRecord
{
    /**
     * @return array<int, Action|ActionGroup>
     */
    protected function getViewPageToolbarActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getInfolistContentComponent(): Component
    {
        $actions = $this->getViewPageToolbarActions();

        if ($actions === []) {
            return parent::getInfolistContentComponent();
        }

        return Group::make([
            EmbeddedSchema::make('infolist'),
            Actions::make($actions)
                ->alignment($this->getFormActionsAlignment())
                ->sticky($this->areFormActionsSticky())
                ->key('view-page-toolbar-actions'),
        ]);
    }

    public function getFormContentComponent(): Component
    {
        $actions = $this->getViewPageToolbarActions();

        if ($actions === []) {
            return parent::getFormContentComponent();
        }

        return Group::make([
            parent::getFormContentComponent(),
            Actions::make($actions)
                ->alignment($this->getFormActionsAlignment())
                ->sticky($this->areFormActionsSticky())
                ->key('view-page-toolbar-actions'),
        ]);
    }
}
