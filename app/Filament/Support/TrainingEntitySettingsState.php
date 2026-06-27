<?php

namespace App\Filament\Support;

final class TrainingEntitySettingsState
{
    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function snapshotRawFormState(array $raw): array
    {
        try {
            $encoded = json_encode($raw, JSON_THROW_ON_ERROR);

            return json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return TrainingEntityFormChangeSummarizer::comparableSnapshot($raw);
        }
    }

    /**
     * @param  array<string, mixed>|null  $baseline
     * @param  array<string, mixed>  $current
     * @param  array<string, string>  $labels
     */
    public static function changesAreEmpty(?array $baseline, array $current, array $labels = []): bool
    {
        if ($baseline === null) {
            return true;
        }

        return TrainingEntityFormChangeSummarizer::describeChanges($baseline, $current, $labels) === [];
    }
}
