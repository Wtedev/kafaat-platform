<?php

namespace App\Services\Training;

use App\Support\PublicDiskPath;

final class TrainingProgramImageUrl
{
    public static function publicUrl(?string $path): string
    {
        return PublicDiskPath::urlOrPlaceholder($path, PublicDiskPath::PLACEHOLDER_TRAINING_CATALOG);
    }
}
