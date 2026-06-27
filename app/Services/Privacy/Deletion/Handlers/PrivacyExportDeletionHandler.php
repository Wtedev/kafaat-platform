<?php

namespace App\Services\Privacy\Deletion\Handlers;

use App\Data\Privacy\DeletionExecutionContext;
use App\Enums\DeletionHandlerName;
use App\Models\PrivacyExportFile;
use App\Services\Privacy\Deletion\Contracts\DeletionHandlerInterface;
use Illuminate\Support\Facades\Storage;

final class PrivacyExportDeletionHandler implements DeletionHandlerInterface
{
    public function name(): string
    {
        return DeletionHandlerName::PrivacyExports->value;
    }

    public function handle(DeletionExecutionContext $context): void
    {
        $exports = PrivacyExportFile::query()
            ->where('user_id', $context->target->id)
            ->whereIn('status', ['pending', 'generating', 'ready', 'failed'])
            ->get();

        foreach ($exports as $export) {
            if (filled($export->path) && filled($export->disk)) {
                $disk = Storage::disk($export->disk);

                if ($disk->exists($export->path)) {
                    $disk->delete($export->path);
                }
            }

            $export->forceFill(['status' => 'deleted'])->saveQuietly();
        }
    }
}
