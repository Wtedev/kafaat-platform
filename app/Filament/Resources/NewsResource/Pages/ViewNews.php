<?php

namespace App\Filament\Resources\NewsResource\Pages;

use App\Filament\Concerns\RendersEntityViewPanel;
use App\Filament\Resources\NewsResource;
use App\Filament\Resources\NewsResource\NewsPublicationFilamentActions;
use App\Filament\Resources\Pages\BaseViewRecord;
use App\Filament\Support\NewsViewPresenter;
use App\Models\News;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Schemas\Schema;

class ViewNews extends BaseViewRecord
{
    use RendersEntityViewPanel;

    protected static string $resource = NewsResource::class;

    public function getTitle(): string
    {
        $title = $this->getRecord()->title;

        return filled($title) ? 'خبر '.$title : parent::getTitle();
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNewsViewPanel(),
        ]);
    }

    protected function getNewsViewPanel(): \Filament\Schemas\Components\Html
    {
        return $this->renderEntityViewPanel(
            fn (): array => NewsViewPresenter::present($this->getRecord()),
        );
    }

    protected function getViewPageToolbarActions(): array
    {
        /** @var Closure(): News $resolveNews */
        $resolveNews = fn (): News => $this->getRecord();

        return [
            EditAction::make(),
            DeleteAction::make(),
            ...NewsPublicationFilamentActions::viewPagePublicationGroup($resolveNews),
        ];
    }
}
