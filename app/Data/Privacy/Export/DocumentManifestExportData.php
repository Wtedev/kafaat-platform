<?php

namespace App\Data\Privacy\Export;

use App\Enums\UserDocumentStatus;
use App\Models\User;
use App\Models\UserDocument;
use App\Services\Documents\CvDocumentService;

final readonly class DocumentManifestExportData
{
    /**
     * @return array<string, mixed>
     */
    public static function forUser(User $user, CvDocumentService $cvService): array
    {
        $cv = $cvService->currentCv($user);

        $documents = UserDocument::query()
            ->where('user_id', $user->id)
            ->where('status', UserDocumentStatus::Active->value)
            ->orderBy('created_at')
            ->get()
            ->map(fn (UserDocument $document): array => [
                'document_type' => $document->document_type?->value ?? (string) $document->document_type,
                'mime_type' => $document->mime_type,
                'size_bytes' => $document->size_bytes,
                'uploaded_at' => $document->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'active_cv_included_in_archive' => $cv !== null,
            'active_cv_mime' => $cv?->mime_type,
            'active_cv_size_bytes' => $cv?->size_bytes,
            'documents' => $documents,
        ];
    }
}
