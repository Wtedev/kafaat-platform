<?php

namespace App\Services\Training;

final class TrainingEntityPublication
{
    public static function canPublishNow(bool $canInlineEdit, bool $tracksPublishedState, bool $isPublished): bool
    {
        if (! $canInlineEdit) {
            return false;
        }

        return $tracksPublishedState ? ! $isPublished : true;
    }
}
