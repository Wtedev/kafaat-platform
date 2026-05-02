<?php

namespace App\Filament\Resources\TrainingProgramResource\Pages;

use App\Enums\ProgramStatus;
use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Filament\Resources\TrainingProgramResource;
use Filament\Schemas\Schema;

class CreateTrainingProgram extends BaseCreateRecord
{
    protected static string $resource = TrainingProgramResource::class;

    public function form(Schema $schema): Schema
    {
        return TrainingProgramResource::createForm($schema);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $visible = (bool) ($data['visible_on_site'] ?? false);
        $data['status'] = $visible
            ? ProgramStatus::Published->value
            : ProgramStatus::Draft->value;

        unset($data['visible_on_site']);

        return $data;
    }
}
