<?php

namespace App\Filament\Resources;

use App\Enums\VolunteerHoursStatus;
use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\VolunteerHourResource\Pages;
use App\Models\VolunteerHour;
use App\Services\VolunteerHoursService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VolunteerHourResource extends Resource
{
    use RegistersNavigationByPermission;

    protected static ?string $model = VolunteerHour::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'التطوع';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'ساعات التطوع';

    protected static ?string $modelLabel = 'سجل ساعات';

    protected static ?string $pluralModelLabel = 'ساعات التطوع';

    protected static function requiredNavigationPermissions(): array
    {
        return ['roles.view'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('المتطوع'),

                Select::make('opportunity_id')
                    ->relationship('opportunity', 'title')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('الفرصة التطوعية'),

                TextInput::make('hours')
                    ->numeric()
                    ->minValue(0.5)
                    ->required()
                    ->label('الساعات')
                    ->suffix('ساعة'),

                Select::make('status')
                    ->label('الحالة')
                    ->options(VolunteerHoursStatus::class)
                    ->required()
                    ->default(VolunteerHoursStatus::Pending->value),

                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable()
                    ->sortable()
                    ->label('المتطوع'),

                TextColumn::make('user.email')
                    ->searchable()
                    ->toggleable()
                    ->label('البريد الإلكتروني'),

                TextColumn::make('opportunity.title')
                    ->searchable()
                    ->sortable()
                    ->label('الفرصة التطوعية'),

                TextColumn::make('hours')
                    ->label('الساعات')
                    ->suffix(' ساعة')
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'warning' => VolunteerHoursStatus::Pending->value,
                        'success' => VolunteerHoursStatus::Approved->value,
                        'danger' => VolunteerHoursStatus::Rejected->value,
                    ])
                    ->sortable(),

                TextColumn::make('approvedBy.name')
                    ->label('اعتمد بواسطة')
                    ->toggleable(),

                TextColumn::make('approved_at')
                    ->label('تاريخ الموافقة')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(VolunteerHoursStatus::class),

                SelectFilter::make('opportunity_id')
                    ->relationship('opportunity', 'title')
                    ->label('الفرصة التطوعية')
                    ->searchable(),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('approve')
                    ->label('موافقة الساعات')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (VolunteerHour $record): bool => $record->status === VolunteerHoursStatus::Pending)
                    ->action(function (VolunteerHour $record): void {
                        app(VolunteerHoursService::class)->approveHours($record, auth()->user());
                        Notification::make()->title('تمت الموافقة على الساعات')->success()->send();
                    }),

                Action::make('reject')
                    ->label('رفض الساعات')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('notes')
                            ->label('ملاحظات (اختياري)')
                            ->rows(3),
                    ])
                    ->visible(fn (VolunteerHour $record): bool => $record->status === VolunteerHoursStatus::Pending)
                    ->action(function (VolunteerHour $record, array $data): void {
                        if (! empty($data['notes'])) {
                            $record->update(['notes' => $data['notes']]);
                        }
                        app(VolunteerHoursService::class)->rejectHours($record, auth()->user());
                        Notification::make()->title('تم رفض الساعات')->warning()->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVolunteerHours::route('/'),
            'view' => Pages\ViewVolunteerHour::route('/{record}'),
        ];
    }
}
