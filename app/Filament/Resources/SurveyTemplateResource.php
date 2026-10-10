<?php

namespace App\Filament\Resources;

use App\Enums\SurveyQuestionType;
use App\Filament\Concerns\BelongsToStaffUiModule;
use App\Filament\Resources\SurveyTemplateResource\Pages;
use App\Models\SurveyTemplate;
use App\Support\StaffUi\StaffUiModule;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SurveyTemplateResource extends Resource
{
    use BelongsToStaffUiModule;

    protected static function staffUiModule(): string
    {
        return StaffUiModule::TRAINING;
    }

    protected static ?string $model = SurveyTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'التدريب';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'قوالب الاستبيانات';

    protected static ?string $modelLabel = 'قالب استبيان';

    protected static ?string $pluralModelLabel = 'قوالب الاستبيانات';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('programs.update');
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('القالب')
                ->schema([
                    TextInput::make('title')
                        ->label('العنوان')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label('الوصف')
                        ->rows(2)
                        ->nullable(),
                    Repeater::make('questions')
                        ->label('الأسئلة')
                        ->relationship()
                        ->orderColumn('position')
                        ->schema([
                            Select::make('type')
                                ->label('النوع')
                                ->options(collect(SurveyQuestionType::cases())->mapWithKeys(
                                    fn (SurveyQuestionType $type): array => [$type->value => $type->label()]
                                )->all())
                                ->required()
                                ->live(),
                            TextInput::make('prompt')
                                ->label('نص السؤال')
                                ->required()
                                ->maxLength(500)
                                ->columnSpanFull(),
                            TextInput::make('scale_min_label')
                                ->label('نص بداية المقياس')
                                ->maxLength(80)
                                ->visible(fn (Get $get): bool => $get('type') === SurveyQuestionType::Scale->value),
                            TextInput::make('scale_max_label')
                                ->label('نص نهاية المقياس')
                                ->maxLength(80)
                                ->visible(fn (Get $get): bool => $get('type') === SurveyQuestionType::Scale->value),
                            TagsInput::make('options')
                                ->label('الخيارات')
                                ->visible(fn (Get $get): bool => in_array($get('type'), [
                                    SurveyQuestionType::Single->value,
                                    SurveyQuestionType::Multiple->value,
                                ], true))
                                ->columnSpanFull(),
                            Toggle::make('required')
                                ->label('إلزامي')
                                ->default(true),
                        ])
                        ->columns(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('العنوان')->searchable(),
                TextColumn::make('questions_count')->counts('questions')->label('الأسئلة'),
            ])
            ->defaultSort('title');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurveyTemplates::route('/'),
            'create' => Pages\CreateSurveyTemplate::route('/create'),
            'edit' => Pages\EditSurveyTemplate::route('/{record}/edit'),
        ];
    }
}
