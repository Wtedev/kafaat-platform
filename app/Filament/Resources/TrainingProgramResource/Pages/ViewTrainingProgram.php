<?php

namespace App\Filament\Resources\TrainingProgramResource\Pages;

use App\Enums\ProgramStatus;
use App\Filament\Actions\TransferTrainingEntityOwnershipAction;
use App\Filament\Resources\Pages\BaseViewRecord;
use App\Filament\Resources\TrainingProgramResource;
use App\Models\TrainingProgram;
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
use Illuminate\Support\Carbon;

class ViewTrainingProgram extends BaseViewRecord
{
    protected static string $resource = TrainingProgramResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->loadMissing(['learningPath', 'owner', 'creator', 'assignee', 'editors']);
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
        return 'معلومات البرنامج';
    }

    public function getContentTabComponent(): Tab
    {
        return $this->getBaseContentTabComponent()
            ->schema([
                $this->getProgramMainDetailsCard(),
                $this->getViewStatisticsSection(),
                Text::make('تعديل البيانات الأساسية وفريق العمل يتم من صفحة التعديل، وإدارة التسجيلات من التبويبات أدناه.')
                    ->size(TextSize::Small)
                    ->color('gray')
                    ->columnSpanFull(),
                $this->getInfolistContentComponent(),
            ]);
    }

    protected function getProgramMainDetailsCard(): Group
    {
        return Group::make([
            Text::make('بيانات البرنامج')
                ->size(TextSize::ExtraSmall)
                ->weight(FontWeight::SemiBold)
                ->color('gray'),
            Section::make()
                ->schema([
                    TextEntry::make('title')
                        ->label('اسم البرنامج')
                        ->columnSpanFull(),

                    TextEntry::make('site_visibility_status')
                        ->label('الظهور في الموقع')
                        ->getStateUsing(function (): string {
                            /** @var TrainingProgram $record */
                            $record = $this->getRecord();

                            return $record->status === ProgramStatus::Published ? 'ظاهر' : 'مخفي';
                        }),

                    TextEntry::make('path_context')
                        ->label('المسار')
                        ->getStateUsing(function (): string {
                            /** @var TrainingProgram $record */
                            $record = $this->getRecord();
                            if ($record->learning_path_id === null) {
                                return 'مستقل';
                            }

                            return $record->learningPath?->title ?? '—';
                        }),

                    TextEntry::make('editors_list')
                        ->label('أعضاء فريق العمل')
                        ->visible(function (): bool {
                            /** @var TrainingProgram $record */
                            $record = $this->getRecord();

                            return $record->learning_path_id === null;
                        })
                        ->getStateUsing(function (): string {
                            /** @var TrainingProgram $record */
                            $record = $this->getRecord();
                            $names = $record->editors->pluck('name')->filter()->values();

                            return $names->isEmpty() ? '—' : $names->implode('، ');
                        })
                        ->columnSpanFull(),

                    TextEntry::make('description')
                        ->label('نبذة')
                        ->placeholder('—')
                        ->columnSpanFull(),

                    TextEntry::make('registration_window_status')
                        ->label('حالة التسجيل')
                        ->getStateUsing(function (): string {
                            return $this->getRecord()->registrationWindowStatusLabel();
                        }),

                    TextEntry::make('program_duration')
                        ->label('مدة البرنامج')
                        ->getStateUsing(function (): string {
                            return $this->getRecord()->programDurationDescription();
                        }),

                    TextEntry::make('start_date')
                        ->label('تاريخ البدء')
                        ->formatStateUsing(function ($state): string {
                            if ($state === null) {
                                return 'غير محدد';
                            }

                            $d = $state instanceof Carbon
                                ? $state
                                : Carbon::parse($state);

                            return $d->translatedFormat('j F Y');
                        }),

                    TextEntry::make('responsible_display')
                        ->label('المسؤول')
                        ->getStateUsing(function (): string {
                            /** @var TrainingProgram $record */
                            $record = $this->getRecord();
                            if ($record->owner_id !== null && $record->owner !== null) {
                                return $record->owner->name;
                            }

                            if ($record->created_by !== null && $record->creator !== null) {
                                return $record->creator->name;
                            }

                            if ($record->assigned_to !== null && $record->assignee !== null) {
                                return $record->assignee->name;
                            }

                            return '—';
                        }),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ])
            ->columnSpanFull()
            ->extraAttributes([
                'class' => 'fi-training-two-col-details rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900',
            ]);
    }

    protected function getViewStatisticsSection(): Group
    {
        return Group::make([
            Grid::make(3)
                ->schema([
                    Section::make()
                        ->schema([
                            TextEntry::make('_label_total')
                                ->hiddenLabel()
                                ->getStateUsing(fn (): string => 'عدد المسجلين')
                                ->color('gray')
                                ->size(TextSize::Small),
                            TextEntry::make('stat_total_registrations')
                                ->hiddenLabel()
                                ->getStateUsing(fn (): string => (string) $this->getRecord()->totalRegistrationsCount())
                                ->weight(FontWeight::Bold)
                                ->size(TextSize::Large),
                        ])
                        ->compact()
                        ->extraAttributes([
                            'class' => 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900',
                        ]),
                    Section::make()
                        ->schema([
                            TextEntry::make('_label_approved')
                                ->hiddenLabel()
                                ->getStateUsing(fn (): string => 'عدد المقبولين')
                                ->color('gray')
                                ->size(TextSize::Small),
                            TextEntry::make('stat_approved_registrations')
                                ->hiddenLabel()
                                ->getStateUsing(fn (): string => (string) $this->getRecord()->approvedRegistrationsCount())
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
                                ->getStateUsing(fn (): string => 'عدد المجتازين')
                                ->color('gray')
                                ->size(TextSize::Small),
                            TextEntry::make('stat_completed_registrations')
                                ->hiddenLabel()
                                ->getStateUsing(fn (): string => (string) $this->getRecord()->completedRegistrationsCount())
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
