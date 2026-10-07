<?php

namespace App\Filament\Resources\LearningPathResource\RelationManagers;

use App\Filament\Resources\LearningPathResource\Pages\ManagePathCertificateDesign;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramCertificatesRelationManager;
use App\Models\LearningPath;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class PathCertificatesRelationManager extends ProgramCertificatesRelationManager
{
    protected static function ownerIsVisible(User $user, Model $owner): bool
    {
        return $owner instanceof LearningPath && $user->can('viewOperational', $owner);
    }

    protected function designPageClass(): string
    {
        return ManagePathCertificateDesign::class;
    }

    /**
     * @return list<TextColumn>
     */
    protected function metricColumns(): array
    {
        return [
            TextColumn::make('completed_courses')
                ->label('الدورات المكتملة')
                ->state(fn (Model $record): string => $this->metricLabel($record)),
        ];
    }
}
