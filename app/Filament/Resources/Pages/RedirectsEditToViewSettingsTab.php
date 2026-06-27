<?php

namespace App\Filament\Resources\Pages;

use Filament\Resources\Pages\Page;
use Livewire\Attributes\Locked;

/**
 * يُحوّل روابط التعديل القديمة إلى تبويب الإعدادات في صفحة العرض.
 */
abstract class RedirectsEditToViewSettingsTab extends Page
{
    protected string $view = 'filament-panels::pages.simple';

    protected static ?string $title = '';

    protected static bool $shouldRegisterNavigation = false;

    #[Locked]
    public int|string $record = '';

    public function mount(int|string $record): void
    {
        $this->redirect(
            static::getResource()::getUrl('view', [
                'record' => $record,
            ]),
            navigate: true,
        );
    }
}
