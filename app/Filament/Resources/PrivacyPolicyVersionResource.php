<?php

namespace App\Filament\Resources;

use App\Enums\PrivacyPolicyVersionStatus;
use App\Filament\Resources\PrivacyPolicyVersionResource\Pages;
use App\Filament\Resources\PrivacyPolicyVersionResource\RelationManagers\AcknowledgementsRelationManager;
use App\Models\PrivacyPolicyVersion;
use App\Services\Privacy\PrivacyPolicyPublisher;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PrivacyPolicyVersionResource extends Resource
{
    protected static ?string $model = PrivacyPolicyVersion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'الحوكمة';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'سياسة الخصوصية';

    protected static ?string $modelLabel = 'إصدار سياسة';

    protected static ?string $pluralModelLabel = 'إصدارات سياسة الخصوصية';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('privacy_policy.view') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('privacy_policy.create') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('privacy_policy.update_draft') && $record instanceof PrivacyPolicyVersion && $record->isDraft();
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('privacy_policy.update_draft') && $record instanceof PrivacyPolicyVersion && $record->isDeletable();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الإصدار')
                ->columns(2)
                ->schema([
                    TextInput::make('version')
                        ->label('رقم الإصدار')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->helperText('مثال: 1.0 أو 2026.1'),

                    TextInput::make('title')
                        ->label('العنوان')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    DateTimePicker::make('effective_at')
                        ->label('تاريخ السريان')
                        ->required()
                        ->default(now()),

                    Toggle::make('requires_reacknowledgement')
                        ->label('يتطلب إعادة إقرار من المستخدمين')
                        ->helperText('عند التفعيل، يُطلب من المستخدمين الحاليين تأكيد الاطلاع قبل متابعة استخدام البوابة.')
                        ->default(false),

                    RichEditor::make('content')
                        ->label('المحتوى')
                        ->required()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version')->label('الإصدار')->searchable()->sortable(),
                TextColumn::make('title')->label('العنوان')->searchable()->limit(40),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (PrivacyPolicyVersionStatus $state): string => $state->label())
                    ->color(fn (PrivacyPolicyVersionStatus $state): string => match ($state) {
                        PrivacyPolicyVersionStatus::Draft => 'gray',
                        PrivacyPolicyVersionStatus::Active => 'success',
                        PrivacyPolicyVersionStatus::Archived => 'warning',
                    }),
                TextColumn::make('effective_at')->label('السريان')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('published_at')->label('النشر')->dateTime('Y-m-d H:i')->sortable(),
                IconColumn::make('requires_reacknowledgement')->label('إعادة إقرار')->boolean(),
                TextColumn::make('createdBy.name')->label('أنشأه')->toggleable(),
                TextColumn::make('updated_at')->label('آخر تحديث')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(collect(PrivacyPolicyVersionStatus::cases())->mapWithKeys(
                        fn (PrivacyPolicyVersionStatus $status): array => [$status->value => $status->label()]
                    )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (PrivacyPolicyVersion $record): bool => static::canEdit($record)),
                Action::make('publish')
                    ->label('نشر')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('نشر إصدار سياسة الخصوصية')
                    ->modalDescription(fn (PrivacyPolicyVersion $record): string => $record->requires_reacknowledgement
                        ? 'سيتم أرشفة الإصدار الفعّال الحالي. هذا الإصدار يتطلب إعادة إقرار من المستخدمين.'
                        : 'سيتم أرشفة الإصدار الفعّال الحالي وتفعيل هذا الإصدار.')
                    ->visible(fn (PrivacyPolicyVersion $record): bool => auth()->user()?->can('privacy_policy.publish') && $record->isDraft())
                    ->action(function (PrivacyPolicyVersion $record): void {
                        app(PrivacyPolicyPublisher::class)->publish($record);
                        Notification::make()->title('تم نشر الإصدار')->success()->send();
                    }),
                Action::make('cloneDraft')
                    ->label('إصدار جديد')
                    ->icon('heroicon-o-document-duplicate')
                    ->visible(fn (PrivacyPolicyVersion $record): bool => auth()->user()?->can('privacy_policy.create')
                        && ! $record->isDraft())
                    ->form([
                        TextInput::make('new_version')
                            ->label('رقم الإصدار الجديد')
                            ->required()
                            ->maxLength(50),
                    ])
                    ->action(function (PrivacyPolicyVersion $record, array $data): void {
                        $draft = app(PrivacyPolicyPublisher::class)->createDraftFromVersion(
                            $record,
                            (string) $data['new_version'],
                            auth()->id(),
                        );

                        Notification::make()->title('تم إنشاء مسودة جديدة')->success()->send();

                        redirect(PrivacyPolicyVersionResource::getUrl('edit', ['record' => $draft]));
                    }),
                DeleteAction::make()->visible(fn (PrivacyPolicyVersion $record): bool => static::canDelete($record)),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AcknowledgementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrivacyPolicyVersions::route('/'),
            'create' => Pages\CreatePrivacyPolicyVersion::route('/create'),
            'edit' => Pages\EditPrivacyPolicyVersion::route('/{record}/edit'),
            'view' => Pages\ViewPrivacyPolicyVersion::route('/{record}'),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('تفاصيل الإصدار')
                ->columns(2)
                ->schema([
                    TextEntry::make('version')->label('الإصدار'),
                    TextEntry::make('title')->label('العنوان'),
                    TextEntry::make('status')->label('الحالة')->formatStateUsing(fn (PrivacyPolicyVersionStatus $state): string => $state->label()),
                    TextEntry::make('effective_at')->label('السريان')->dateTime('Y-m-d H:i'),
                    TextEntry::make('published_at')->label('النشر')->dateTime('Y-m-d H:i'),
                    TextEntry::make('requires_reacknowledgement')->label('إعادة إقرار')->formatStateUsing(fn (bool $state): string => $state ? 'نعم' : 'لا'),
                    TextEntry::make('content_hash')->label('بصمة المحتوى')->columnSpanFull(),
                    TextEntry::make('content')->label('المحتوى')->html()->columnSpanFull(),
                ]),
        ]);
    }
}
