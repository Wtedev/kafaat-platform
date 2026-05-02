<?php

namespace App\Filament\Resources\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords as FilamentListRecords;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;

/**
 * يعرض إجراءات القائمة (مثل «إنشاء») فوق الجدول بدل هيدر الصفحة.
 */
abstract class BaseListRecords extends FilamentListRecords
{
    /**
     * @return array<int, Action|ActionGroup>
     */
    protected function getListPageToolbarActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function content(Schema $schema): Schema
    {
        $toolbarActions = $this->getListPageToolbarActions();

        $leadingToolbar = $toolbarActions === []
            ? []
            : [
                Actions::make($toolbarActions)
                    ->alignment($this->getFormActionsAlignment())
                    ->sticky($this->areFormActionsSticky())
                    ->key('list-records-toolbar-actions'),
            ];

        return $schema
            ->components([
                ...$leadingToolbar,
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }
}
