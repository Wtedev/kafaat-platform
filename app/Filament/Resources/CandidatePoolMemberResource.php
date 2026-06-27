<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CandidatePoolMemberResource\Pages\ListCandidatePoolMembers;
use App\Models\User;
use App\Services\CandidatePool\CandidatePoolQuery;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CandidatePoolMemberResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'المستفيدون';

    protected static ?string $navigationLabel = 'قاعدة المرشحين';

    protected static ?string $modelLabel = 'مرشح';

    protected static ?string $pluralModelLabel = 'قاعدة المرشحين';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('candidate_pool.view') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return app(CandidatePoolQuery::class)->eligibleQuery()->with(['profile', 'candidatePoolPreference']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fullName')->label('الاسم')->state(fn (User $record): string => $record->fullName()),
                TextColumn::make('profile.city')->label('المدينة'),
                TextColumn::make('profile.job_title')->label('المسمى'),
                TextColumn::make('profile.currentCvDocument.uploaded_at')->label('تحديث السيرة')->dateTime('Y-m-d'),
                TextColumn::make('candidatePoolPreference.current_status')->label('الموافقة')->badge(),
            ])
            ->recordActions([
                Action::make('viewProfile')
                    ->label('عرض الملف')
                    ->url(fn (User $record): string => UserResource::getUrl('view', ['record' => $record]))
                    ->visible(fn (): bool => auth()->user()?->can('candidate_pool.profile.view') ?? false),
                Action::make('downloadCv')
                    ->label('تنزيل السيرة')
                    ->url(fn (User $record): string => route('admin.beneficiaries.cv-file.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => auth()->user()?->can('candidate_pool.cv.download') ?? false),
            ])
            ->paginated([25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCandidatePoolMembers::route('/'),
        ];
    }
}
