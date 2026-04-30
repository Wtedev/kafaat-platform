<?php

namespace App\Filament\Resources\LearningPathResource\RelationManagers;

use App\Enums\ProgramStatus;
use App\Models\TrainingProgram;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TrainingProgramsRelationManager extends RelationManager
{
    protected static string $relationship = 'programs';

    protected static ?string $title = 'البرامج التدريبية';

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
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('البرنامج')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'gray'    => ProgramStatus::Draft->value,
                        'success' => ProgramStatus::Published->value,
                        'warning' => ProgramStatus::Archived->value,
                    ]),

                TextColumn::make('start_date')
                    ->label('بداية البرنامج')
                    ->date()
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('نهاية البرنامج')
                    ->date()
                    ->toggleable(),

                TextColumn::make('registrations_count')
                    ->counts('registrations')
                    ->label('المسجلون')
                    ->badge()
                    ->color('info'),

                TextColumn::make('capacity')
                    ->label('الطاقة')
                    ->default('غير محدودة'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(ProgramStatus::class),
            ])
            ->headerActions([
                CreateAction::make()->label('إنشاء برنامج جديد'),
            ])
            ->actions([
                EditAction::make()->label('تعديل'),

                Action::make('detach')
                    ->label('فصل عن المسار')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('فصل البرنامج عن المسار')
                    ->modalDescription('سيتم إزالة هذا البرنامج من المسار التعليمي. يمكن إعادة ربطه لاحقاً من صفحة البرنامج.')
                    ->modalSubmitActionLabel('نعم، فصل')
                    ->action(function (TrainingProgram $record): void {
                        $record->update(['learning_path_id' => null]);
                        Notification::make()->title('تم فصل البرنامج عن المسار')->success()->send();
                    }),

                DeleteAction::make()->label('حذف'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('start_date');
    }
}
