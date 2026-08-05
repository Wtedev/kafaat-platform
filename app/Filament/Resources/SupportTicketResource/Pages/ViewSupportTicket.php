<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Pages\SupportInbox;
use App\Filament\Resources\Pages\BaseViewRecord;
use App\Filament\Resources\SupportTicketResource;

class ViewSupportTicket extends BaseViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->redirect(
            SupportInbox::getUrl(['selected' => $this->getRecord()->getKey()], panel: 'admin'),
        );
    }
}
