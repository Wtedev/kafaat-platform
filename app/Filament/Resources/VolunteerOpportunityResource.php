<?php

namespace App\Filament\Resources;

use App\Enums\OpportunityStatus;
use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\VolunteerOpportunityResource\Pages;
use App\Filament\Resources\VolunteerOpportunityResource\RelationManagers\RegistrationsRelationManager;
use App\Filament\Resources\VolunteerOpportunityResource\RelationManagers\VolunteerHoursRelationManager;
use App\Models\VolunteerOpportunity;
use App\Support\FilamentAssignmentVisibility;
use App\Support\PublicDiskPath;
use App\Support\StaffFilamentRoles;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VolunteerOpportunityResource extends Resource
{
    use RegistersNavigationByPermission;

    protected static ?string $model = VolunteerOpportunity::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-hand-raised';

    protected static string|\UnitEnum|null $navigationGroup = 'التطوع';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'الفرص التطوعية';

    protected static ?string $modelLabel = 'فرصة تطوعية';

    protected static ?string $pluralModelLabel = 'الفرص التطوعية';

    protected static function requiredNavigationPermissions(): array
    {
        return ['volunteering.view'];
    }

    /**
     * مسار عام لمعاينة صورة الفرصة (تخزين محلي).
     */
    public static function resolveVolunteerOpportunityImagePublicUrl(?string $path): string
    {
        return PublicDiskPath::urlOrPlaceholder($path, PublicDiskPath::PLACEHOLDER_VOLUNTEER_OPPORTUNITY);
    }

    public static function volunteerOpportunityImageUploadField(): FileUpload
    {
        return FileUpload::make('image')
            ->label('صورة الفرصة')
            ->image()
            ->disk('public')
            ->directory('volunteer-opportunities/images')
            ->visibility('public')
            ->maxSize(4096)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->imagePreviewHeight('12rem')
            ->imageResizeMode('cover')
            ->nullable()
            ->helperText('JPEG أو PNG أو WebP — حتى 4 ميجابايت. اختياري.')
            ->columnSpanFull();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('تفاصيل الفرصة')->columns(2)->schema([
                TextInput::make('title')
                    ->label('العنوان')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('slug')
                    ->label('الرابط المختصر')
                    ->required()
                    ->maxLength(255),

                Select::make('status')
                    ->label('الحالة')
                    ->options(OpportunityStatus::class)
                    ->required()
                    ->default(OpportunityStatus::Draft->value),

                Select::make('assigned_to')
                    ->label('مسؤول الفرصة')
                    ->relationship('assignee', 'name', modifyQueryUsing: fn (Builder $query) => $query->role(StaffFilamentRoles::assignableVolunteeringCoordinatorRoleNames()))
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => FilamentAssignmentVisibility::bypasses(auth()->user()))
                    ->required(fn (): bool => FilamentAssignmentVisibility::bypasses(auth()->user()))
                    ->dehydrated(fn (): bool => FilamentAssignmentVisibility::bypasses(auth()->user()))
                    ->helperText('يحدد مدير التطوع الذي يدير هذه الفرصة في لوحة الإدارة.'),

                Textarea::make('description')
                    ->label('الوصف')
                    ->rows(4)
                    ->columnSpanFull(),

                static::volunteerOpportunityImageUploadField(),
            ]),

            Section::make('الطاقة والجدول')->columns(2)->schema([
                TextInput::make('capacity')
                    ->label('الطاقة الاستيعابية (فارغاً = غير محدود)')
                    ->numeric()
                    ->minValue(1),

                TextInput::make('hours_expected')
                    ->label('الساعات المطلوبة')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('ساعة'),

                Grid::make(2)->schema([
                    DatePicker::make('start_date')
                        ->label('تاريخ البداية'),

                    DatePicker::make('end_date')
                        ->label('تاريخ الانتهاء')
                        ->afterOrEqual('start_date'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('صورة')
                    ->getStateUsing(fn (VolunteerOpportunity $record): string => $record->imagePublicUrl())
                    ->checkFileExistence(false)
                    ->square()
                    ->imageSize(40)
                    ->toggleable(),

                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('assignee.name')
                    ->label('المسؤول')
                    ->toggleable()
                    ->searchable(),

                BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'gray' => OpportunityStatus::Draft->value,
                        'success' => OpportunityStatus::Published->value,
                        'warning' => OpportunityStatus::Archived->value,
                    ])
                    ->sortable(),

                TextColumn::make('capacity')
                    ->label('الطاقة')
                    ->default('غير محدودة'),

                TextColumn::make('registrations_count')
                    ->counts('registrations')
                    ->label('عدد المتطوعين')
                    ->badge()
                    ->color('info'),

                TextColumn::make('hours_expected')
                    ->label('الساعات المطلوبة')
                    ->suffix(' ساعة')
                    ->toggleable(),

                TextColumn::make('start_date')
                    ->label('تاريخ البداية')
                    ->date()
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('تاريخ الانتهاء')
                    ->date()
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
                    ->options(OpportunityStatus::class),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->forFilamentAssignmentAccess(auth()->user()))
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('الفرصة التطوعية')->schema([
                ImageEntry::make('image')
                    ->label('صورة الغلاف')
                    ->getStateUsing(fn (VolunteerOpportunity $record): string => $record->imagePublicUrl())
                    ->checkFileExistence(false)
                    ->imageHeight('14rem')
                    ->columnSpanFull(),

                TextEntry::make('title')
                    ->label('العنوان')
                    ->columnSpanFull(),

                TextEntry::make('slug')
                    ->label('الرابط المختصر'),

                TextEntry::make('status')
                    ->label('الحالة')
                    ->formatStateUsing(fn (?OpportunityStatus $state): string => $state?->label() ?? '—')
                    ->badge()
                    ->color(fn (?OpportunityStatus $state): string => match ($state) {
                        OpportunityStatus::Published => 'success',
                        OpportunityStatus::Archived => 'warning',
                        default => 'gray',
                    }),

                TextEntry::make('assignee.name')
                    ->label('مسؤول الفرصة')
                    ->placeholder('—'),

                TextEntry::make('description')
                    ->label('الوصف')
                    ->placeholder('—')
                    ->columnSpanFull(),

                TextEntry::make('capacity')
                    ->label('الطاقة الاستيعابية')
                    ->placeholder('غير محدودة'),

                TextEntry::make('hours_expected')
                    ->label('الساعات المطلوبة')
                    ->suffix(' ساعة')
                    ->placeholder('—'),

                TextEntry::make('start_date')
                    ->label('تاريخ البداية')
                    ->date('Y/m/d')
                    ->placeholder('—'),

                TextEntry::make('end_date')
                    ->label('تاريخ الانتهاء')
                    ->date('Y/m/d')
                    ->placeholder('—'),

                TextEntry::make('published_at')
                    ->label('تاريخ النشر')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('—'),
            ])
                ->columns(2),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RegistrationsRelationManager::class,
            VolunteerHoursRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVolunteerOpportunities::route('/'),
            'create' => Pages\CreateVolunteerOpportunity::route('/create'),
            'view' => Pages\ViewVolunteerOpportunity::route('/{record}'),
            'edit' => Pages\EditVolunteerOpportunity::route('/{record}/edit'),
        ];
    }
}
