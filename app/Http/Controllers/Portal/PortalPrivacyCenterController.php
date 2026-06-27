<?php

namespace App\Http\Controllers\Portal;

use App\Data\Privacy\PortalPrivacyCenterViewData;
use App\Http\Controllers\Controller;
use App\Services\CandidatePool\CandidatePoolConsentService;
use App\Services\Documents\CvDocumentService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PortalPrivacyCenterController extends Controller
{
    public function __construct(
        private readonly CvDocumentService $cvDocumentService,
        private readonly CandidatePoolConsentService $candidatePoolConsentService,
    ) {}

    public function show(Request $request): View
    {
        $user = $request->user();

        if ($user->isAnonymized() || $user->account_status?->value === 'deletion_processing') {
            throw new AccessDeniedHttpException('لا يمكن الوصول إلى مركز الخصوصية لهذا الحساب.');
        }

        $viewData = PortalPrivacyCenterViewData::forUser(
            $user,
            $this->cvDocumentService,
            $this->candidatePoolConsentService,
        );

        return view('portal.privacy.index', ['privacy' => $viewData]);
    }
}
