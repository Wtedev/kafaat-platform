<?php

namespace App\Filament\Resources\PartnerResource\Pages;

use App\Filament\Concerns\RendersEntityViewPanel;
use App\Filament\Resources\Pages\BaseViewRecord;
use App\Filament\Resources\PartnerResource;
use App\Filament\Support\PartnerViewPresenter;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Schemas\Schema;

class ViewPartner extends BaseViewRecord
{
    use RendersEntityViewPanel;

    protected static string $resource = PartnerResource::class;

    public function getTitle(): string
    {
        $name = $this->getRecord()->name;

        return filled($name) ? 'شريك '.$name : parent::getTitle();
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            $this->getPartnerViewPanel(),
        ]);
    }

    protected function getPartnerViewPanel(): \Filament\Schemas\Components\Html
    {
        return $this->renderEntityViewPanel(
            fn (): array => PartnerViewPresenter::present($this->getRecord()),
        );
    }

    protected function getViewPageToolbarActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
