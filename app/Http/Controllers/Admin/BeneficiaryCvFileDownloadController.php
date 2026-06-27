<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\CvDocumentService;
use App\Enums\AuditLogResult;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BeneficiaryCvFileDownloadController extends Controller
{
    public function __construct(
        private readonly CvDocumentService $cvDocuments,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function __invoke(Request $request, User $user): StreamedResponse
    {
        $this->authorize('downloadCv', $user);
        abort_unless($user->isPortalUser(), 404);

        $document = $this->cvDocuments->currentCv($user);

        if ($document === null) {
            abort(404);
        }

        try {
            $response = $this->cvDocuments->downloadResponse($user, $document, $request->user(), $request);

            $this->auditLogger->recordOrFail(
                $request->user(),
                'cv.downloaded',
                AuditLogResult::Success,
                $user,
                $document,
                request: $request,
            );

            return $response;
        } catch (InvalidArgumentException $exception) {
            $this->auditLogger->record(
                $request->user(),
                'cv.download_denied',
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
