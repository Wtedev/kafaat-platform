<?php

namespace App\Filament\Concerns;

use Filament\Schemas\Components\Html;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;

trait RendersEntityViewPanel
{
    /**
     * @param  callable(): array{stats: array<int, array{label: string, value: string, icon: string}>, sections: array<int, array<string, mixed>>}  $resolvePresented
     */
    protected function renderEntityViewPanel(callable $resolvePresented): Html
    {
        return Html::make(function () use ($resolvePresented): HtmlString {
            $presented = $resolvePresented();

            return new HtmlString(
                View::make('filament.components.entity-view-panel', $presented)->render(),
            );
        })
            ->columnSpanFull()
            ->key(fn (): string => 'kafaat-entity-view-'.$this->getRecord()->getKey().'-'.($this->getRecord()->updated_at?->getTimestamp() ?? 0));
    }
}
