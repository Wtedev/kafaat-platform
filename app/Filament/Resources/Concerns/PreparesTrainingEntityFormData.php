<?php

namespace App\Filament\Resources\Concerns;

trait PreparesTrainingEntityFormData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stampTrainingEntityAuditFields(array $data): array
    {
        $userId = auth()->id();

        if ($userId === null) {
            return $data;
        }

        if (! filled($data['created_by'] ?? null)) {
            $data['created_by'] = $userId;
        }

        $data['updated_by'] = $userId;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function dropEmptyTrainingSlug(array $data): array
    {
        if (blank($data['slug'] ?? null)) {
            unset($data['slug']);
        }

        return $data;
    }
}
