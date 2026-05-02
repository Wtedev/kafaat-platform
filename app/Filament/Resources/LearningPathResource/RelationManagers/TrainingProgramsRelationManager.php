<?php

namespace App\Filament\Resources\LearningPathResource\RelationManagers;

use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Filament\Resources\TrainingProgramResource;
use App\Models\LearningPath;
use App\Models\TrainingProgram;
use App\Support\FilamentAssignmentVisibility;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class TrainingProgramsRelationManager extends RelationManager
{
    protected static string $relationship = 'programs';

    protected static ?string $title = 'البرامج في المسار';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($ownerRecord instanceof LearningPath) {
            return $user->can('view', $ownerRecord);
        }

        return parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('العنوان')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            TextInput::make('slug')
                ->label('الرابط المختصر')
                ->maxLength(255)
                ->helperText('اتركه فارغاً للتوليد التلقائي من العنوان'),

            Select::make('status')
                ->label('الحالة')
                ->options(ProgramStatus::class)
                ->required()
                ->default(ProgramStatus::Draft->value),

            Select::make('program_kind')
                ->label('نوع البرنامج')
                ->options(TrainingProgramKind::class)
                ->required()
                ->default(TrainingProgramKind::Course->value),

            TextInput::make('capacity')
                ->label('الطاقة الاستيعابية')
                ->numeric()
                ->minValue(1)
                ->helperText('اتركه فارغاً لعدد غير محدود'),

            Grid::make(2)->schema([
                DatePicker::make('start_date')
                    ->label('بداية البرنامج'),

                DatePicker::make('end_date')
                    ->label('نهاية البرنامج')
                    ->afterOrEqual('start_date'),
            ])->columnSpanFull(),

            Grid::make(2)->schema([
                DatePicker::make('registration_start')
                    ->label('بداية التسجيل'),

                DatePicker::make('registration_end')
                    ->label('نهاية التسجيل')
                    ->afterOrEqual('registration_start'),
            ])->columnSpanFull(),

            Textarea::make('description')
                ->label('الوصف')
                ->rows(3)
                ->columnSpanFull(),

            Select::make('assigned_to')
                ->label('منسق البرنامج (تشغيلي)')
                ->relationship('assignee', 'name', modifyQueryUsing: fn (Builder $query) => $query->role('training_manager'))
                ->searchable()
                ->preload()
                ->visible(fn (): bool => FilamentAssignmentVisibility::bypasses(auth()->user()))
                ->required(fn (): bool => FilamentAssignmentVisibility::bypasses(auth()->user()))
                ->dehydrated(fn (): bool => FilamentAssignmentVisibility::bypasses(auth()->user()))
                ->helperText('منفصل عن المسؤول — المالك (owner_id). يُملأ من إعدادات البرنامج الرئيسية عند الحاجة.'),
        ]);
    }

    public function table(Table $table): Table
    {
        /** @var LearningPath $path */
        $path = $this->getOwnerRecord();

        return $table
            ->heading('')
            ->modifyQueryUsing(function (Builder $query, bool $isResolvingRecord = false): Builder {
                return $query->withCount('registrations');
            })
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان البرنامج')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('program_kind')
                    ->label('نوع البرنامج')
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof TrainingProgramKind) {
                            return $state->label();
                        }

                        return TrainingProgramKind::tryFrom((string) $state)?->label() ?? '—';
                    }),

                BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'gray' => ProgramStatus::Draft->value,
                        'success' => ProgramStatus::Published->value,
                        'warning' => ProgramStatus::Archived->value,
                    ])
                    ->formatStateUsing(function ($state): ?string {
                        if ($state instanceof ProgramStatus) {
                            return $state->label();
                        }

                        return ProgramStatus::tryFrom((string) $state)?->label();
                    })
                    ->sortable(),

                TextColumn::make('registrations_count')
                    ->label('عدد المسجلين')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('إنشاء برنامج جديد في المسار')
                    ->modalHeading('إنشاء برنامج جديد')
                    ->authorize(fn (): bool => (auth()->user()?->can('updateContainerStructure', $path) ?? false)
                        && (auth()->user()?->can('create', TrainingProgram::class) ?? false))
                    ->mutateFormDataUsing(function (array $data) use ($path): array {
                        $data['learning_path_id'] = $path->getKey();
                        $max = (int) TrainingProgram::query()
                            ->where('learning_path_id', $path->getKey())
                            ->max('path_sort_order');
                        $data['path_sort_order'] = $max > 0 ? $max + 1 : 1;

                        return $data;
                    }),

                Action::make('attachExistingProgram')
                    ->label('ربط برنامج موجود')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->modalHeading('ربط برنامج بالمسار')
                    ->modalDescription('يُربط برنامج غير مرتبط بمسار. يجب أن يكون لديك حق تعديل البرنامج لقبول الربط.')
                    ->form([
                        Select::make('training_program_id')
                            ->label('البرنامج')
                            ->options(function (): array {
                                $user = auth()->user();
                                if ($user === null) {
                                    return [];
                                }

                                return TrainingProgram::query()
                                    ->whereNull('learning_path_id')
                                    ->orderBy('title')
                                    ->get()
                                    ->filter(fn (TrainingProgram $p) => $user->can('update', $p))
                                    ->mapWithKeys(fn (TrainingProgram $p) => [$p->getKey() => $p->title])
                                    ->all();
                            })
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->authorize(fn (): bool => auth()->user()?->can('updateContainerStructure', $path) ?? false)
                    ->action(function (array $data) use ($path): void {
                        Gate::authorize('updateContainerStructure', $path);
                        $program = TrainingProgram::query()->findOrFail($data['training_program_id']);
                        Gate::authorize('update', $program);
                        $max = (int) TrainingProgram::query()
                            ->where('learning_path_id', $path->getKey())
                            ->max('path_sort_order');
                        $program->update([
                            'learning_path_id' => $path->getKey(),
                            'path_sort_order' => $max > 0 ? $max + 1 : 1,
                        ]);
                        Notification::make()->title('تم ربط البرنامج بالمسار')->success()->send();
                    }),
            ])
            ->actions([
                Action::make('editProgramContent')
                    ->label('تعديل محتوى البرنامج')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->url(fn (TrainingProgram $record): string => TrainingProgramResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab()
                    ->visible(fn (TrainingProgram $record): bool => auth()->user()?->can('update', $record) ?? false),

                Action::make('detach')
                    ->label('فصل من المسار')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('فصل البرنامج عن المسار')
                    ->modalDescription('سيتم إزالة هذا البرنامج من المسار التعليمي. يمكن إعادة ربطه لاحقاً.')
                    ->modalSubmitActionLabel('نعم، فصل')
                    ->visible(fn (): bool => auth()->user()?->can('updateContainerStructure', $path) ?? false)
                    ->action(function (TrainingProgram $record) use ($path): void {
                        Gate::authorize('updateContainerStructure', $path);
                        if ((int) $record->learning_path_id !== (int) $path->getKey()) {
                            return;
                        }
                        $record->update([
                            'learning_path_id' => null,
                            'path_sort_order' => null,
                        ]);
                        Notification::make()->title('تم فصل البرنامج عن المسار')->success()->send();
                    }),
            ])
            ->reorderable(
                'path_sort_order',
                fn (): bool => auth()->user()?->can('updateContainerStructure', $path) ?? false
            )
            ->authorizeReorder(fn (): bool => auth()->user()?->can('updateContainerStructure', $path) ?? false)
            ->defaultSort('path_sort_order');
    }
}
