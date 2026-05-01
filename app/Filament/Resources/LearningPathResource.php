<?php

namespace App\Filament\Resources;

use App\Enums\PathStatus;
use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\LearningPathResource\Pages;
use App\Filament\Resources\LearningPathResource\RelationManagers\CoursesRelationManager;
use App\Filament\Resources\LearningPathResource\RelationManagers\PathRegistrationsRelationManager;
use App\Filament\Resources\LearningPathResource\RelationManagers\TrainingProgramsRelationManager;
use App\Models\LearningPath;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LearningPathResource extends Resource
{
    use RegistersNavigationByPermission;

    protected static ?string $model = LearningPath::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|\UnitEnum|null $navigationGroup = 'التدريب';

    protected static ?string $navigationLabel = 'المسارات';

    protected static ?string $modelLabel = 'مسار تعليمي';

    protected static ?string $pluralModelLabel = 'المسارات';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    protected static function requiredNavigationPermissions(): array
    {
        return ['paths.view'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('تفاصيل المسار')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label('العنوان')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('slug')
                        ->label('الرابط المختصر')
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText('اتركه فارغاً للتوليد التلقائي'),

                    TextInput::make('capacity')
                        ->label('الطاقة الاستيعابية')
                        ->numeric()
                        ->minValue(1)
                        ->nullable()
                        ->helperText('اتركه فارغاً لعدد غير محدود'),

                    Select::make('status')
                        ->label('الحالة')
                        ->options(PathStatus::class)
                        ->required()
                        ->default(PathStatus::Draft->value),

                    DateTimePicker::make('published_at')
                        ->label('تاريخ النشر')
                        ->nullable(),

                    Select::make('created_by')
                        ->relationship('creator', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->label('أنشأ بواسطة'),

                    Textarea::make('description')
                        ->label('الوصف')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'warning' => PathStatus::Draft->value,
                        'success' => PathStatus::Published->value,
                        'danger' => PathStatus::Archived->value,
                    ])
                    ->sortable(),

                TextColumn::make('capacity')
                    ->label('الطاقة')
                    ->default('غير محدودة')
                    ->sortable(),

                TextColumn::make('courses_count')
                    ->counts('courses')
                    ->label('عدد المقررات'),

                TextColumn::make('registrations_count')
                    ->counts('registrations')
                    ->label('التسجيلات'),

                TextColumn::make('creator.name')
                    ->label('أنشأ بواسطة')
                    ->toggleable(),

                TextColumn::make('published_at')
                    ->label('تاريخ النشر')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(PathStatus::class),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            CoursesRelationManager::class,
            TrainingProgramsRelationManager::class,
            PathRegistrationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLearningPaths::route('/'),
            'create' => Pages\CreateLearningPath::route('/create'),
            'view' => Pages\ViewLearningPath::route('/{record}'),
            'edit' => Pages\EditLearningPath::route('/{record}/edit'),
        ];
    }
}
