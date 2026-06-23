<?php

namespace App\Filament\Resources\LearningPathResource\Pages;

use App\Enums\PathStatus;
use App\Filament\Actions\TransferTrainingEntityOwnershipAction;
use App\Filament\Resources\LearningPathResource;
use App\Filament\Resources\Pages\BaseViewRecord;
use App\Models\LearningPath;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class ViewLearningPath extends BaseViewRecord
{
    protected static string $resource = LearningPathResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->loadCount(['programs', 'registrations', 'completedPathRegistrations']);
        $this->getRecord()->loadMissing(['owner', 'creator']);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'معلومات المسار';
    }

    public function getContentTabComponent(): Tab
    {
        return $this->getBaseContentTabComponent()
            ->schema([
                $this->getPathMainDetailsCard(),
                $this->getViewStatisticsSection(),
                Text::make('تعديل البيانات الأساسية يتم من صفحة التعديل، وإدارة البرامج والمسجلين والفريق من التبويبات أدناه.')
                    ->size(TextSize::Small)
                    ->color('gray')
                    ->columnSpanFull(),
                $this->getInfolistContentComponent(),
            ]);
    }

    protected function getPathMainDetailsCard(): Group
    {
        return Group::make([
            Text::make('بيانات المسار')
                ->size(TextSize::ExtraSmall)
                ->weight(FontWeight::SemiBold)
                ->color('gray'),
            Section::make()
                ->schema([
                    TextEntry::make('title')
                        ->label('اسم المسار')
                        ->columnSpanFull(),

                    TextEntry::make('description')
                        ->label('نبذة')
                        ->placeholder('—')
                        ->columnSpanFull(),

                    TextEntry::make('path_publication_status')
                        ->label('الظهور في الموقع')
                        ->getStateUsing(function (): string {
                            /** @var LearningPath $record */
                            $record = $this->getRecord();

                            return $record->status === PathStatus::Published ? 'ظاهر' : 'مخفي';
                        }),

                    TextEntry::make('responsible_display')
                        ->label('المسؤول')
                        ->getStateUsing(function (): string {
                            /** @var LearningPath $record */
                            $record = $this->getRecord();
                            if ($record->owner_id !== null && $record->owner !== null) {
                                return $record->owner->name;
                            }

                            if ($record->created_by !== null && $record->creator !== null) {
                                return $record->creator->name;
                            }

                            return '—';
                        }),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ])
            ->columnSpanFull()
            ->extraAttributes([
                'class' => 'fi-learning-path-two-col-details rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900',
            ]);
    }

    protected function getViewStatisticsSection(): Group
    {
        return Group::make([
            Grid::make(3)
                ->schema([
                    Section::make()
                        ->schema([
                            TextEntry::make('_label_programs')
                                ->hiddenLabel()
                                ->getStateUsing(fn (): string => 'عدد البرامج في المسار')
                                ->color('gray')
                                ->size(TextSize::Small),
                            TextEntry::make('stat_programs_count')
                                ->hiddenLabel()
                                ->getStateUsing(fn (): string => (string) $this->getRecord()->programs_count)
                                ->weight(FontWeight::Bold)
                                ->size(TextSize::Large),
                        ])
                        ->compact()
                        ->extraAttributes([
                            'class' => 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900',
                        ]),
                    Section::make()
                        ->schema([
                            TextEntry::make('_label_registrations')
                                ->hiddenLabel()
                                ->getStateUsing(fn (): string => 'عدد المسجلين في المسار')
                                ->color('gray')
                                ->size(TextSize::Small),
                            TextEntry::make('stat_registrations_count')
                                ->hiddenLabel()
                                ->getStateUsing(fn (): string => (string) $this->getRecord()->registrations_count)
                                ->weight(FontWeight::Bold)
                                ->size(TextSize::Large),
                        ])
                        ->compact()
                        ->extraAttributes([
                            'class' => 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900',
                        ]),
                    Section::make()
                        ->schema([
                            TextEntry::make('_label_completed')
                                ->hiddenLabel()
                                ->getStateUsing(fn (): string => 'عدد المجتازين في المسار')
                                ->color('gray')
                                ->size(TextSize::Small),
                            TextEntry::make('stat_completed_count')
                                ->hiddenLabel()
                                ->getStateUsing(fn (): string => (string) $this->getRecord()->completed_path_registrations_count)
                                ->weight(FontWeight::Bold)
                                ->size(TextSize::Large),
                        ])
                        ->compact()
                        ->extraAttributes([
                            'class' => 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900',
                        ]),
                ]),
        ])
            ->columnSpanFull();
    }

    protected function getViewPageToolbarActions(): array
    {
        return [
            TransferTrainingEntityOwnershipAction::make($this),
            EditAction::make()
                ->visible(fn (): bool => auth()->user()?->can('update', $this->getRecord()) ?? false),
            DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->can('delete', $this->getRecord()) ?? false),
        ];
    }
}
