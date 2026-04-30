<?php

namespace App\Filament\Resources;

use App\Enums\RegistrationStatus;
use App\Exceptions\PathCapacityExceededException;
use App\Filament\Resources\PathRegistrationResource\Pages;
use App\Models\PathRegistration;
use App\Services\PathRegistrationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PathRegistrationResource extends Resource
{
    protected static ?string $model = PathRegistration::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'التدريب';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'تسجيلات المسارات';

    protected static ?string $modelLabel = 'تسجيل مسار';

    protected static ?string $pluralModelLabel = 'تسجيلات المسارات';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('learning_path_id')
                    ->relationship('learningPath', 'title')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('المسار التعليمي'),

                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('المستخدم'),

                Select::make('status')
                    ->label('الحالة')
                    ->options(RegistrationStatus::class)
                    ->required(),

                Textarea::make('rejected_reason')
                    ->rows(3)
                    ->columnSpanFull()
                    ->label('سبب الرفض'),
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
                    ->label('المستخدم'),

                TextColumn::make('user.email')
                    ->searchable()
                    ->toggleable()
                    ->label('البريد الإلكتروني'),

                TextColumn::make('learningPath.title')
                    ->searchable()
                    ->sortable()
                    ->label('المسار التعليمي'),

                BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'warning' => RegistrationStatus::Pending->value,
                        'success' => RegistrationStatus::Approved->value,
                        'danger'  => RegistrationStatus::Rejected->value,
                        'gray'    => RegistrationStatus::Cancelled->value,
                        'info'    => RegistrationStatus::Completed->value,
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

                TextColumn::make('completed_at')
                    ->label('تاريخ الإكمال')
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
                    ->options(RegistrationStatus::class),

                SelectFilter::make('learning_path_id')
                    ->relationship('learningPath', 'title')
                    ->label('المسار التعليمي')
                    ->searchable(),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('approve')
                    ->label('موافقة')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PathRegistration $record): bool => $record->status === RegistrationStatus::Pending)
                    ->action(function (PathRegistration $record): void {
                        try {
                            app(PathRegistrationService::class)->approve($record, auth()->user());
                            Notification::make()
                                ->title('تمت الموافقة على التسجيل')
                                ->success()
                                ->send();
                        } catch (PathCapacityExceededException) {
                            Notification::make()
                                ->title('المسار بلغ طاقته القصوى')
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('reject')
                    ->label('رفض')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('rejected_reason')
                            ->label('سبب الرفض (اختياري)')
                            ->rows(3),
                    ])
                    ->visible(fn (PathRegistration $record): bool => $record->status === RegistrationStatus::Pending)
                    ->action(function (PathRegistration $record, array $data): void {
                        app(PathRegistrationService::class)->reject(
                            $record,
                            $data['rejected_reason'] ?? null
                        );
                        Notification::make()
                            ->title('تم رفض التسجيل')
                            ->warning()
                            ->send();
                    }),

                Action::make('complete')
                    ->label('تحديد كمكتمل')
                    ->icon('heroicon-o-trophy')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (PathRegistration $record): bool => $record->status === RegistrationStatus::Approved)
                    ->action(function (PathRegistration $record): void {
                        app(PathRegistrationService::class)->complete($record);
                        Notification::make()
                            ->title('تم تحديد التسجيل كمكتمل')
                            ->success()
                            ->send();
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
            'index' => Pages\ListPathRegistrations::route('/'),
            'view'  => Pages\ViewPathRegistration::route('/{record}'),
        ];
    }
}
