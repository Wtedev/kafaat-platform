<?php

namespace App\Filament\Resources\TrainingProgramResource\RelationManagers;

use App\Enums\ProgramPrepDayType;
use App\Models\ProgramPrepDay;
use App\Models\TrainingProgram;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProgramPrepDaysRelationManager extends RelationManager
{
    protected static string $relationship = 'prepDays';

    protected static ?string $title = 'أيام التحضير';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if ($user === null || ! ($ownerRecord instanceof TrainingProgram)) {
            return false;
        }

        return $user->can('viewOperational', $ownerRecord);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('prep_date')
                ->label('التاريخ')
                ->required()
                ->native(false),

            Select::make('delivery_type')
                ->label('نوع اليوم')
                ->options(ProgramPrepDayType::options())
                ->required()
                ->live()
                ->default(ProgramPrepDayType::InPerson->value),

            Toggle::make('requires_attendance')
                ->label('يتطلب تحضير')
                ->helperText('أيام عن بُعد لا تُحسب في نسبة الحضور إلا عند تفعيل هذا الخيار.')
                ->default(fn (Get $get): bool => $get('delivery_type') === ProgramPrepDayType::InPerson->value)
                ->dehydrated(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('prep_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('label')
                    ->label('اليوم')
                    ->getStateUsing(fn (ProgramPrepDay $record): string => $record->displayLabel()),

                TextColumn::make('delivery_type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof ProgramPrepDayType
                        ? $state->label()
                        : (ProgramPrepDayType::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->color(fn ($state): string => match (true) {
                        $state === ProgramPrepDayType::InPerson,
                        $state === ProgramPrepDayType::InPerson->value => 'success',
                        default => 'gray',
                    }),

                IconColumn::make('requires_attendance')
                    ->label('يتطلب تحضير')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('إضافة يوم')
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->mutateFormDataUsing(function (array $data): array {
                        if (! array_key_exists('requires_attendance', $data) || $data['requires_attendance'] === null) {
                            $data['requires_attendance'] = ($data['delivery_type'] ?? null) === ProgramPrepDayType::InPerson->value;
                        }

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false),
                DeleteAction::make()
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false),
            ])
            ->defaultSort('prep_date');
    }
}
