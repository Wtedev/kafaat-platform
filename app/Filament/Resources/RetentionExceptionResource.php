<?php

namespace App\Filament\Resources;

use App\Enums\RetentionExceptionReasonCode;
use App\Enums\RetentionExceptionScope;
use App\Enums\RetentionExceptionStatus;
use App\Filament\Resources\RetentionExceptionResource\Pages;
use App\Models\RetentionException;
use App\Services\Privacy\Retention\RetentionExceptionManagementService;
use App\Services\Privacy\Retention\RetentionResourceCatalog;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RetentionExceptionResource extends Resource
{
    protected static ?string $model = RetentionException::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string|\UnitEnum|null $navigationGroup = 'الحوكمة';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'استثناءات الاحتفاظ';

    protected static ?string $modelLabel = 'استثناء احتفاظ';

    protected static ?string $pluralModelLabel = 'استثناءات الاحتفاظ';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('retention_exceptions.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('retention_exceptions.manage') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        $catalog = app(RetentionResourceCatalog::class);
        $resourceOptions = collect($catalog->definitions())
            ->filter(fn ($definition) => $definition->allowsLegalHold)
            ->mapWithKeys(fn ($definition) => [$definition->code => $definition->label])
            ->all();

        return $schema->components([
            Select::make('resource_type')->label('نوع المورد')->options($resourceOptions)->required(),
            Select::make('scope')->label('النطاق')->options(collect(RetentionExceptionScope::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all())->required(),
            TextInput::make('resource_id')->label('معرّف المورد')->numeric()->nullable(),
            TextInput::make('user_id')->label('معرّف المستخدم')->numeric()->nullable(),
            Select::make('reason_code')->label('رمز السبب')->options(collect(RetentionExceptionReasonCode::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all())->required(),
            Textarea::make('reason')->label('السبب')->required()->rows(3),
            DateTimePicker::make('starts_at')->label('يبدأ')->required(),
            DateTimePicker::make('ends_at')->label('ينتهي')->nullable(),
            DateTimePicker::make('review_at')->label('تاريخ المراجعة')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('uuid')->label('المرجع')->copyable(),
                TextColumn::make('scope')->label('النطاق')->formatStateUsing(fn ($state) => $state?->label()),
                TextColumn::make('resource_type')->label('المورد'),
                TextColumn::make('reason_code')->label('رمز السبب')->formatStateUsing(fn ($state) => $state?->label()),
                TextColumn::make('starts_at')->label('يبدأ')->dateTime('Y-m-d H:i'),
                TextColumn::make('ends_at')->label('ينتهي')->dateTime('Y-m-d H:i')->placeholder('—'),
                TextColumn::make('review_at')->label('مراجعة')->dateTime('Y-m-d H:i'),
                TextColumn::make('status')->label('الحالة')->badge()->formatStateUsing(fn ($state) => $state?->label()),
                TextColumn::make('approver.name')->label('اعتمد بواسطة'),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('إلغاء')
                    ->requiresConfirmation()
                    ->visible(fn (RetentionException $record): bool => $record->status === RetentionExceptionStatus::Active)
                    ->action(function (RetentionException $record): void {
                        app(RetentionExceptionManagementService::class)->revoke($record, auth()->user());
                        Notification::make()->title('تم إلغاء الاستثناء')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRetentionExceptions::route('/'),
            'create' => Pages\CreateRetentionException::route('/create'),
        ];
    }
}
