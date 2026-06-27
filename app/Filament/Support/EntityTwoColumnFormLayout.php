<?php

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

/**
 * تخطيط عمودين موحّد: صورة الغلاف يسار الشاشة، أقسام النموذج يميناً (LTR للشبكة فقط).
 */
final class EntityTwoColumnFormLayout
{
    /**
     * @param  array<int, Component>  $detailSections
     */
    public static function wrap(
        Schema $schema,
        FileUpload $imageField,
        array $detailSections,
        string $imageColumnLabel = 'صورة الغلاف',
        string $mode = 'create',
    ): Schema {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 2])
                ->columnSpanFull()
                ->extraAttributes([
                    'class' => "fi-entity-{$mode}-layout items-start gap-6 lg:gap-8 fi-entity-two-col-ltr",
                ])
                ->schema([
                    Group::make([
                        Text::make($imageColumnLabel)
                            ->size(TextSize::ExtraSmall)
                            ->weight(FontWeight::SemiBold)
                            ->color('gray'),
                        $imageField,
                    ])
                        ->columnSpan(1)
                        ->extraAttributes([
                            'class' => 'fi-entity-two-col-image rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900',
                        ]),
                    Group::make()
                        ->schema($detailSections)
                        ->columnSpan(1)
                        ->extraAttributes([
                            'class' => 'fi-entity-two-col-details flex min-w-0 flex-col gap-6',
                        ]),
                ]),
        ]);
    }
}
