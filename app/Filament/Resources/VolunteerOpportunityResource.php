<?php

namespace App\Filament\Resources;

use App\Enums\OpportunityStatus;
use App\Filament\Concerns\ConfiguresViewFirstResourceTable;
use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\VolunteerOpportunityResource\Pages;
use App\Filament\Resources\VolunteerOpportunityResource\RelationManagers\RegistrationsRelationManager;
use App\Filament\Resources\VolunteerOpportunityResource\RelationManagers\VolunteerHoursRelationManager;
use App\Filament\Support\EntityTwoColumnFormLayout;
use App\Filament\Support\TrainingEntityFormSupport;
use App\Models\VolunteerOpportunity;
use App\Support\PublicDiskPath;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
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
    use ConfiguresViewFirstResourceTable;
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

    public static function resolveVolunteerOpportunityImagePublicUrl(?string $path): string
    {
        return PublicDiskPath::urlOrPlaceholder($path, PublicDiskPath::PLACEHOLDER_VOLUNTEER_OPPORTUNITY);
    }

    public static function volunteerOpportunityImageUploadField(): FileUpload
    {
        return TrainingEntityFormSupport::coverImageUpload(
            directory: 'volunteer-opportunities/images',
            label: 'صورة الفرصة',
            previewHeight: '12rem',
        );
    }

    public static function createForm(Schema $schema): Schema
    {
        return EntityTwoColumnFormLayout::wrap(
            $schema,
            static::volunteerOpportunityImageUploadField(),
            static::volunteerCreateSections(),
            imageColumnLabel: 'صورة الفرصة',
            mode: 'create',
        );
    }

    public static function editForm(Schema $schema): Schema
    {
        return EntityTwoColumnFormLayout::wrap(
            $schema,
            static::volunteerOpportunityImageUploadField(),
            static::volunteerEditSections(),
            imageColumnLabel: 'صورة الفرصة',
            mode: 'edit',
        );
    }

    /**
     * @return array<int, Component>
     */
    protected static function volunteerCreateSections(): array
    {
        return [
            static::volunteerBasicSection(),
            TrainingEntityFormSupport::publicationSection(OpportunityStatus::Published),
            TrainingEntityFormSupport::volunteerScheduleSection(),
            TrainingEntityFormSupport::advancedVolunteerSettingsSection(),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function volunteerEditSections(): array
    {
        return [
            static::volunteerBasicSection(),
            TrainingEntityFormSupport::publicationSection(OpportunityStatus::Published, forEdit: true),
            TrainingEntityFormSupport::volunteerScheduleSection(),
            TrainingEntityFormSupport::advancedVolunteerSettingsSection(),
        ];
    }

    protected static function volunteerBasicSection(): Section
    {
        return Section::make('البيانات الأساسية')
            ->columns(2)
            ->schema([
                TextInput::make('title')
                    ->label('اسم الفرصة')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('slug')
                    ->label('الرابط المختصر')
                    ->maxLength(255)
                    ->helperText('يُنشأ تلقائياً من العنوان إذا تُرك فارغاً')
                    ->columnSpanFull(),

                TrainingEntityFormSupport::descriptionField(),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return static::createForm($schema);
    }

    public static function table(Table $table): Table
    {
        return static::applyViewFirstTable($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['assignee', 'creator'])->withCount('registrations'))
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
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'gray' => OpportunityStatus::Draft->value,
                        'success' => OpportunityStatus::Published->value,
                        'warning' => OpportunityStatus::Archived->value,
                    ])
                    ->sortable(),

                TextColumn::make('start_date')
                    ->label('البداية')
                    ->date('Y/m/d')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('end_date')
                    ->label('النهاية')
                    ->date('Y/m/d')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('capacity')
                    ->label('الطاقة')
                    ->default('غير محدودة')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? 'غير محدودة' : (string) $state)
                    ->toggleable(),

                TextColumn::make('registrations_count')
                    ->counts('registrations')
                    ->label('عدد المتطوعين')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(OpportunityStatus::class),
            ])
            ->actions([
                DeleteAction::make()
                    ->color('danger')
                    ->visible(fn (VolunteerOpportunity $record): bool => auth()->user()?->can('delete', $record) ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
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
                    ->formatStateUsing(fn (?int $state): string => $state === null ? 'غير محدودة' : (string) $state)
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
}
