<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CertificateResource\Pages;
use App\Models\Certificate;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CertificateResource extends Resource
{
    protected static ?string $model = Certificate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'الشهادات';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'الشهادات';

    protected static ?string $modelLabel = 'شهادة';

    protected static ?string $pluralModelLabel = 'الشهادات';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('المستخدم'),

                TextInput::make('certificate_number')
                    ->required()
                    ->maxLength(255)
                    ->label('رقم الشهادة'),

                TextInput::make('verification_code')
                    ->maxLength(255)
                    ->label('رمز التحقق'),

                TextInput::make('certificateable_type')
                    ->label('نوع المصدر')
                    ->readOnly(),

                TextInput::make('certificateable_id')
                    ->numeric()
                    ->label('معرف المصدر')
                    ->readOnly(),

                DateTimePicker::make('issued_at')
                    ->label('تاريخ الإصدار'),

                TextInput::make('file_path')
                    ->label('مسار الملف')
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
                    ->label('المستخدم'),

                TextColumn::make('user.email')
                    ->searchable()
                    ->toggleable()
                    ->label('البريد الإلكتروني'),

                TextColumn::make('certificate_number')
                    ->searchable()
                    ->sortable()
                    ->label('رقم الشهادة'),

                TextColumn::make('verification_code')
                    ->label('رمز التحقق')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('certificateable_type')
                    ->label('نوع الشهادة')
                    ->formatStateUsing(fn (?string $state): string => match (class_basename((string) $state)) {
                        'TrainingProgram' => 'برنامج تدريبي',
                        'LearningPath' => 'مسار تعليمي',
                        'VolunteerOpportunity' => 'فرصة تطوعية',
                        default => $state ? class_basename($state) : '—',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match (class_basename((string) $state)) {
                        'TrainingProgram' => 'success',
                        'LearningPath' => 'info',
                        'VolunteerOpportunity' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('issued_at')
                    ->label('تاريخ الإصدار')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('file_path')
                    ->label('الملف')
                    ->formatStateUsing(fn (?string $state): string => $state ? 'نعم' : 'لا')
                    ->badge()
                    ->color(fn (?string $state): string => $state ? 'success' : 'gray'),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->relationship('user', 'name')
                    ->label('المستخدم')
                    ->searchable(),
            ])
            ->actions([
                ViewAction::make()->label('عرض'),

                Action::make('verify')
                    ->label('رابط التحقق')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->url(fn (Certificate $record): string => route('certificates.verify', $record->verification_code))
                    ->openUrlInNewTab(),

                Action::make('download')
                    ->label('تحميل PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn (Certificate $record): bool => $record->file_path !== null)
                    ->url(fn (Certificate $record): string => $record->fileUrl())
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('issued_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCertificates::route('/'),
            'view' => Pages\ViewCertificate::route('/{record}'),
        ];
    }
}
