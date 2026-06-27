<?php

namespace App\Http\Controllers\Portal;

use App\Enums\CandidatePoolConsentSource;
use App\Http\Controllers\Controller;
use App\Services\CandidatePool\CandidatePoolConsentService;
use App\Services\CandidatePool\CandidatePoolConsentVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PortalCandidatePoolConsentController extends Controller
{
    public function __construct(
        private readonly CandidatePoolConsentService $consentService,
    ) {}

    public function prompted(Request $request): RedirectResponse|Response
    {
        $version = CandidatePoolConsentVersionService::activeVersion();
        if ($version === null) {
            return $request->expectsJson() ? response()->noContent() : back();
        }

        $this->consentService->recordPrompted(
            $request->user(),
            $version,
            CandidatePoolConsentSource::ProfilePopup,
            $request,
        );

        return $request->expectsJson() ? response()->noContent() : back();
    }

    public function grant(Request $request): RedirectResponse
    {
        $version = CandidatePoolConsentVersionService::activeVersion();
        abort_if($version === null, 503);

        $this->consentService->grant($request->user(), $version, CandidatePoolConsentSource::ProfilePopup, $request);

        return back()->with('success', 'تم تسجيل انضمامك إلى قاعدة المرشحين.');
    }

    public function decline(Request $request): RedirectResponse
    {
        $version = CandidatePoolConsentVersionService::activeVersion();
        abort_if($version === null, 503);

        $this->consentService->decline($request->user(), $version, CandidatePoolConsentSource::ProfilePopup, $request);

        return back()->with('success', 'تم تسجيل اختيارك بعدم الانضمام.');
    }
}
