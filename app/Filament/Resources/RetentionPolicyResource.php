<?php

namespace App\Filament\Resources;

use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionPolicyStatus;
use App\Enums\RetentionTriggerEvent;
use App\Filament\Resources\RetentionPolicyResource\Pages;
use App\Models\RetentionPolicy;
use App\Services\Privacy\Retention\RetentionPolicyEngine;
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

class RetentionPolicyResource extends Resource
{
    protected static ?string $model = RetentionPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'الحوكمة';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'سياسات الاحتفاظ';

    protected static ?string $modelLabel = 'سياسة احتفاظ';

    protected static ?string $pluralModelLabel = 'سياسات الاحتفاظ';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('retention_policies.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('retention_policies.create') || auth()->user()?->can('retention_policies.manage') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof RetentionPolicy
            && $record->isEditable()
            && (auth()->user()?->can('retention_policies.update_draft') || auth()->user()?->can('retention_policies.manage'));
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        $catalog = app(RetentionResourceCatalog::class);
        $resourceOptions = collect($catalog->definitions())
            ->mapWithKeys(fn ($definition) => [$definition->code => $definition->label])
            ->all();

        return $schema->components([
            Select::make('resource_type')
                ->label('نوع المورد')
                ->options($resourceOptions)
                ->required()
                ->disabled(fn (?RetentionPolicy $record): bool => $record !== null),
            TextInput::make('name')->label('الاسم')->required(),
            Textarea::make('description')->label('الوصف')->rows(3),
            Select::make('trigger_type')
                ->label('المُشغِّل')
                ->options(collect(RetentionTriggerEvent::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all())
                ->required(),
            Select::make('action')
                ->label('الإجراء')
                ->options(collect(RetentionPolicyAction::cases())->mapWithKeys(fn ($case) => [$case->value => $case->value])->all())
                ->required(),
            TextInput::make('retention_period_days')->label('مدة الاحتفاظ (أيام)')->numeric()->nullable(),
            TextInput::make('grace_period_days')->label('فترة السماح (أيام)')->numeric()->default(0),
            DateTimePicker::make('effective_at')->label('تاريخ السريان')->nullable(),
            Textarea::make('reason')->label('سبب السياسة')->required()->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('الاسم')->searchable(),
                TextColumn::make('resource_type')->label('المورد'),
                TextColumn::make('action')->label('الإجراء'),
                TextColumn::make('trigger_type')->label('المُشغِّل'),
                TextColumn::make('retention_period_days')->label('المدة')->placeholder('—'),
                TextColumn::make('status')->label('الحالة')->badge()->formatStateUsing(fn ($state) => $state?->label()),
                TextColumn::make('effective_at')->label('السريان')->dateTime('Y-m-d H:i')->placeholder('—'),
                TextColumn::make('last_previewed_at')->label('آخر معاينة')->dateTime('Y-m-d H:i')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('معاينة')
                    ->visible(fn (RetentionPolicy $record): bool => auth()->user()?->can('retention_policies.preview') === true)
                    ->action(function (RetentionPolicy $record): void {
                        $run = app(RetentionPolicyEngine::class)->preview($record);
                        Notification::make()
                            ->title('اكتملت المعاينة')
                            ->body(sprintf('مؤهل: %d | مستبعد: %d', $run->eligible_count, $run->excluded_count))
                            ->success()
                            ->send();
                    }),
                Action::make('activate')
                    ->label('تفعيل')
                    ->requiresConfirmation()
                    ->visible(fn (RetentionPolicy $record): bool => auth()->user()?->can('retention_policies.activate') === true && $record->status === RetentionPolicyStatus::Draft)
                    ->action(function (RetentionPolicy $record): void {
                        app(RetentionPolicyEngine::class)->activate($record, auth()->user());
                        Notification::make()->title('تم تفعيل السياسة')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRetentionPolicies::route('/'),
            'create' => Pages\CreateRetentionPolicy::route('/create'),
            'edit' => Pages\EditRetentionPolicy::route('/{record}/edit'),
        ];
    }
}
