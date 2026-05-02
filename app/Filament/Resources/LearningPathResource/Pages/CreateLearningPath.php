<?php

namespace App\Filament\Resources\LearningPathResource\Pages;

use App\Enums\PathStatus;
use App\Filament\Resources\LearningPathResource;
use App\Filament\Resources\Pages\BaseCreateRecord;
use Filament\Schemas\Schema;

class CreateLearningPath extends BaseCreateRecord
{
    protected static string $resource = LearningPathResource::class;

    public function form(Schema $schema): Schema
    {
        return LearningPathResource::createForm($schema);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $visible = (bool) ($data['visible_on_site'] ?? false);
        $data['status'] = $visible
            ? PathStatus::Published->value
            : PathStatus::Draft->value;

        unset($data['visible_on_site']);

        return $data;
    }
}
