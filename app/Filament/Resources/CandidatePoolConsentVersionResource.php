<?php

namespace App\Filament\Resources;

use App\Enums\PrivacyPolicyVersionStatus;
use App\Filament\Resources\CandidatePoolConsentVersionResource\Pages;
use App\Models\CandidatePoolConsentVersion;
use App\Services\CandidatePool\CandidatePoolConsentPublisher;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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

class CandidatePoolConsentVersionResource extends Resource
{
    protected static ?string $model = CandidatePoolConsentVersion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'الحوكمة';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'موافقة قاعدة المرشحين';

    protected static ?string $modelLabel = 'إصدار موافقة';

    protected static ?string $pluralModelLabel = 'إصدارات موافقة قاعدة المرشحين';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('candidate_pool.consent_versions.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny() && $record instanceof CandidatePoolConsentVersion && $record->isDraft();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny() && $record instanceof CandidatePoolConsentVersion && $record->isDeletable();
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
                        ->unique(ignoreRecord: true),

                    TextInput::make('title')
                        ->label('العنوان')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    DateTimePicker::make('effective_at')
                        ->label('تاريخ السريان')
                        ->required()
                        ->default(now()),

                    Toggle::make('requires_reconsent')
                        ->label('يتطلب إعادة موافقة')
                        ->default(false),

                    RichEditor::make('content')
                        ->label('نص الموافقة')
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
                TextColumn::make('title')->label('العنوان')->limit(40),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (PrivacyPolicyVersionStatus $state): string => $state->label()),
                TextColumn::make('effective_at')->label('السريان')->dateTime('Y-m-d H:i'),
                IconColumn::make('requires_reconsent')->label('إعادة موافقة')->boolean(),
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
                EditAction::make()->visible(fn (CandidatePoolConsentVersion $record): bool => static::canEdit($record)),
                Action::make('publish')
                    ->label('نشر')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (CandidatePoolConsentVersion $record): string => $record->requires_reconsent
                        ? 'سيتم أرشفة الإصدار الفعّال. المستخدمون الموافقون على إصدار أقدم لن يظهرون حتى يوافقوا مجدداً.'
                        : 'سيتم أرشفة الإصدار الفعّال وتفعيل هذا الإصدار.')
                    ->visible(fn (CandidatePoolConsentVersion $record): bool => static::canViewAny() && $record->isDraft())
                    ->action(function (CandidatePoolConsentVersion $record): void {
                        app(CandidatePoolConsentPublisher::class)->publish($record);
                        Notification::make()->title('تم نشر الإصدار')->success()->send();
                    }),
                Action::make('cloneDraft')
                    ->label('إصدار جديد')
                    ->icon('heroicon-o-document-duplicate')
                    ->visible(fn (CandidatePoolConsentVersion $record): bool => static::canViewAny() && ! $record->isDraft())
                    ->form([
                        TextInput::make('new_version')->label('رقم الإصدار الجديد')->required()->maxLength(50),
                    ])
                    ->action(function (CandidatePoolConsentVersion $record, array $data): void {
                        $draft = app(CandidatePoolConsentPublisher::class)->createDraftFromVersion(
                            $record,
                            (string) $data['new_version'],
                            auth()->id(),
                        );

                        Notification::make()->title('تم إنشاء مسودة')->success()->send();
                        redirect(static::getUrl('edit', ['record' => $draft]));
                    }),
                DeleteAction::make()->visible(fn (CandidatePoolConsentVersion $record): bool => static::canDelete($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCandidatePoolConsentVersions::route('/'),
            'create' => Pages\CreateCandidatePoolConsentVersion::route('/create'),
            'edit' => Pages\EditCandidatePoolConsentVersion::route('/{record}/edit'),
            'view' => Pages\ViewCandidatePoolConsentVersion::route('/{record}'),
        ];
    }
}
