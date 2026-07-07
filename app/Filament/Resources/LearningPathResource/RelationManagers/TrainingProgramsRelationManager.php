<?php

namespace App\Filament\Resources\LearningPathResource\RelationManagers;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Filament\Resources\TrainingProgramResource;
use App\Models\LearningPath;
use App\Models\TrainingProgram;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Filament\Forms\Components\Select;

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
        /** @var LearningPath $path */
        $path = $this->getOwnerRecord();

        return TrainingProgramResource::createForm($schema, $path->getKey());
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

                TextColumn::make('competency_track')
                    ->label('مسار الكفاءة')
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof CompetencyTrack) {
                            return $state->shortLabel();
                        }

                        return CompetencyTrack::tryFrom((string) $state)?->shortLabel() ?? '—';
                    }),

                TextColumn::make('delivery_mode')
                    ->label('التنفيذ')
                    ->getStateUsing(fn (TrainingProgram $record): string => $record->deliveryModeDescription() ?? '—'),

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
                Action::make('createProgramInPath')
                    ->label('إنشاء برنامج جديد في المسار')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->authorize(fn (): bool => (auth()->user()?->can('updateContainerStructure', $path) ?? false)
                        && (auth()->user()?->can('create', TrainingProgram::class) ?? false))
                    ->url(fn (): string => TrainingProgramResource::createUrlForLearningPath($path)),

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
                            'capacity' => null,
                            'registration_start' => null,
                            'registration_end' => null,
                            'weekdays' => null,
                        ]);
                        Notification::make()->title('تم ربط البرنامج بالمسار')->success()->send();
                    }),
            ])
            ->actions([
                Action::make('editProgramContent')
                    ->label('إعدادات البرنامج')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('gray')
                    ->url(fn (TrainingProgram $record): string => TrainingProgramResource::getUrl('view', [
                        'record' => $record,
                        'relation' => 'settings',
                    ]))
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
            ->defaultSort('title');
    }
}
