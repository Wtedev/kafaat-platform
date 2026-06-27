<?php

namespace App\Filament\Support;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;

final class EntityPublicationFormData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergePublicationUiState(
        array $data,
        Model $record,
        BackedEnum $publishedStatus,
    ): array {
        $data['publish_immediately'] = TrainingEntityFormSupport::resolvePublishImmediatelyFromRecord(
            $record->status,
            $record->published_at,
            $publishedStatus,
        );
        $data['published_at'] = $record->published_at?->timezone(config('app.timezone'))->format('Y-m-d');

        return $data;
    }
}
