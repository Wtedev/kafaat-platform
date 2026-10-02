<?php

namespace App\Filament\Resources\VolunteerOpportunityResource\RelationManagers;

use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramCertificatesRelationManager;
use App\Filament\Resources\VolunteerOpportunityResource\Pages\ManageVolunteerCertificateDesign;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class VolunteerCertificatesRelationManager extends ProgramCertificatesRelationManager
{
    protected static function ownerIsVisible(User $user, Model $owner): bool
    {
        return $owner instanceof VolunteerOpportunity && $user->can('update', $owner);
    }

    protected function designPageClass(): string
    {
        return ManageVolunteerCertificateDesign::class;
    }

    /**
     * @return list<TextColumn>
     */
    protected function metricColumns(): array
    {
        return [
            TextColumn::make('approved_hours')
                ->label('الساعات المعتمدة')
                ->state(fn (Model $record): string => $this->metricLabel($record)),
        ];
    }
}
