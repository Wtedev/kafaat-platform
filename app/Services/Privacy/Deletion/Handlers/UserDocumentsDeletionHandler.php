<?php

namespace App\Services\Privacy\Deletion\Handlers;

use App\Data\Privacy\DeletionExecutionContext;
use App\Enums\DeletionHandlerName;
use App\Enums\UserDocumentStatus;
use App\Models\Profile;
use App\Models\UserDocument;
use App\Services\Documents\PrivateDocumentsStorage;
use App\Services\Privacy\Deletion\Contracts\DeletionHandlerInterface;
use Illuminate\Support\Facades\Storage;

final class UserDocumentsDeletionHandler implements DeletionHandlerInterface
{
    public function name(): string
    {
        return DeletionHandlerName::UserDocuments->value;
    }

    public function handle(DeletionExecutionContext $context): void
    {
        $user = $context->target;

        $documents = UserDocument::query()
            ->where('user_id', $user->id)
            ->where('status', UserDocumentStatus::Active)
            ->get();

        foreach ($documents as $document) {
            $this->deleteDocumentFile($document);
            $document->forceFill([
                'status' => UserDocumentStatus::Deleted,
                'deleted_at' => now(),
            ])->saveQuietly();
        }

        Profile::query()
            ->where('user_id', $user->id)
            ->update([
                'current_cv_document_id' => null,
                'cv_path' => null,
            ]);
    }

    private function deleteDocumentFile(UserDocument $document): void
    {
        if ($document->path === null || $document->path === '') {
            return;
        }

        $disk = Storage::disk($document->disk);

        if ($disk->exists($document->path)) {
            $disk->delete($document->path);
        }
    }
}
