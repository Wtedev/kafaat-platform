<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ConfiguresEditOnlyResourceTable;
use App\Filament\Resources\GovernanceDocumentResource\Pages;
use App\Models\GovernanceDocument;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GovernanceDocumentResource extends Resource
{
    use ConfiguresEditOnlyResourceTable;

    protected static ?string $model = GovernanceDocument::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    protected static string|\UnitEnum|null $navigationGroup = 'الحوكمة';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'وثائق الحوكمة';

    protected static ?string $modelLabel = 'وثيقة';

    protected static ?string $pluralModelLabel = 'وثائق الحوكمة';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->can('manage_governance') || $user->hasRole(['super_admin', 'admin']));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
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
            Section::make('بيانات الوثيقة')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label('العنوان')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Select::make('type')
                        ->label('النوع')
                        ->options(GovernanceDocument::TYPES)
                        ->required(),

                    DatePicker::make('document_date')
                        ->label('تاريخ الوثيقة')
                        ->nullable(),

                    TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->numeric()
                        ->default(0)
                        ->required()
                        ->minValue(0),

                    Textarea::make('description')
                        ->label('الوصف')
                        ->rows(3)
                        ->maxLength(1000)
                        ->nullable()
                        ->columnSpanFull(),

                    FileUpload::make('file_path')
                        ->label('ملف الوثيقة')
                        ->disk('public')
                        ->directory('governance/documents')
                        ->visibility('public')
                        ->acceptedFileTypes(['application/pdf', 'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                        ->maxSize(20480)
                        ->nullable()
                        ->helperText('PDF أو Word — حتى 20 ميجابايت')
                        ->columnSpanFull(),

                    TextInput::make('file_url')
                        ->label('رابط خارجي للملف')
                        ->url()
                        ->maxLength(500)
                        ->nullable()
                        ->helperText('بديل عن رفع الملف')
                        ->columnSpanFull(),

                    FileUpload::make('cover_image')
                        ->label('صورة الغلاف')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->disk('public')
                        ->directory('governance/covers')
                        ->visibility('public')
                        ->maxSize(4096)
                        ->nullable()
                        ->columnSpanFull(),

                    Toggle::make('is_active')
                        ->label('منشور')
                        ->default(true)
                        ->inline(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::applyEditOnlyTable($table)
            ->defaultSort('sort_order', 'asc')
            ->columns([
                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->sortable()
                    ->limit(60),

                TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->getStateUsing(fn (GovernanceDocument $record): string => $record->typeLabel())
                    ->sortable(),

                TextColumn::make('document_date')
                    ->label('تاريخ الوثيقة')
                    ->date('Y/m/d')
                    ->sortable()
                    ->default('—'),

                IconColumn::make('is_active')
                    ->label('منشور')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                TextColumn::make('created_at')
                    ->label('أُنشئ في')
                    ->date('Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('النوع')
                    ->options(GovernanceDocument::TYPES),
                SelectFilter::make('is_active')
                    ->label('الحالة')
                    ->options([
                        '1' => 'منشور',
                        '0' => 'غير منشور',
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): void {
                        $v = $data['value'] ?? null;
                        if ($v === null || $v === '') {
                            return;
                        }
                        $query->where('is_active', $v === '1');
                    }),
            ])
            ->actions([
                EditAction::make()->color('gray'),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListGovernanceDocuments::route('/'),
            'create' => Pages\CreateGovernanceDocument::route('/create'),
            'edit'   => Pages\EditGovernanceDocument::route('/{record}/edit'),
        ];
    }
}
