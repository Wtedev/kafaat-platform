<?php

namespace App\Filament\Resources\LearningPathResource\RelationManagers;

use App\Filament\Resources\CertificateResource;
use App\Models\Certificate;
use App\Models\LearningPath;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PathCertificatesRelationManager extends RelationManager
{
    protected static string $relationship = 'certificates';

    protected static ?string $title = 'الشهادات';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($ownerRecord instanceof LearningPath) {
            return $user->can('viewOperational', $ownerRecord);
        }

        return parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
            ->columns([
                TextColumn::make('user.name')
                    ->label('المستفيد')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')
                    ->label('البريد الإلكتروني')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('certificate_number')
                    ->label('رقم الشهادة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('verification_code')
                    ->label('رمز التحقق')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('issued_at')
                    ->label('تاريخ الإصدار')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('file_path')
                    ->label('ملف PDF')
                    ->formatStateUsing(fn (?string $state): string => $state ? 'نعم' : 'لا')
                    ->badge()
                    ->color(fn (?string $state): string => $state ? 'success' : 'gray'),
            ])
            ->actions([
                Action::make('view')
                    ->label('عرض')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Certificate $record): string => CertificateResource::getUrl('view', ['record' => $record]))
                    ->visible(fn (Certificate $record): bool => auth()->user()?->can('view', $record) ?? false),

                Action::make('verify')
                    ->label('رابط التحقق')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->url(fn (Certificate $record): string => route('certificates.verify', $record->verification_code))
                    ->openUrlInNewTab()
                    ->visible(fn (Certificate $record): bool => auth()->user()?->can('view', $record) ?? false),

                Action::make('download')
                    ->label('تحميل PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(function (Certificate $record): bool {
                        if ($record->file_path === null) {
                            return false;
                        }

                        return auth()->user()?->can('download', $record) ?? false;
                    })
                    ->url(fn (Certificate $record): string => $record->fileUrl() ?? '#')
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('issued_at', 'desc');
    }
}
