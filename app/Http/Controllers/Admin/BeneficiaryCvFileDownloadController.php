<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Documents\CvDocumentService;
use App\Enums\AuditLogResult;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BeneficiaryCvFileDownloadController extends Controller
{
    public function __construct(
        private readonly CvDocumentService $cvDocuments,
        private readonly AuditLogService $auditLog,
    ) {}

    public function __invoke(Request $request, User $user): StreamedResponse
    {
        abort_unless(
            $request->user()?->can('beneficiary.cv.download') || $request->user()?->can('candidate_pool.cv.download'),
            403
        );
        abort_unless($user->isPortalUser(), 404);

        $document = $this->cvDocuments->currentCv($user);

        if ($document === null) {
            abort(404);
        }

        try {
            return $this->cvDocuments->downloadResponse($user, $document, $request->user(), $request);
        } catch (InvalidArgumentException $exception) {
            $this->auditLog->record(
                $request->user(),
                'cv.downloaded',
                AuditLogResult::Failure,
                $user,
                $document,
                reason: $exception->getMessage(),
                request: $request,
            );

            abort(404);
        }
    }
}
