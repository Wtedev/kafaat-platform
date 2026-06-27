<?php

namespace App\Http\Controllers\Portal;

use App\Enums\CandidatePoolConsentSource;
use App\Http\Controllers\Controller;
use App\Services\CandidatePool\CandidatePoolConsentService;
use App\Services\CandidatePool\CandidatePoolConsentVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalCandidatePoolSettingsController extends Controller
{
    public function __construct(
        private readonly CandidatePoolConsentService $consentService,
    ) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $user->load('candidatePoolPreference');

        return view('portal.candidate-pool-settings', [
            'preference' => $user->candidatePoolPreference,
            'activeVersion' => CandidatePoolConsentVersionService::activeVersion(),
            'consentText' => $this->consentService->consentText(),
            'hasCv' => app(\App\Services\Documents\CvDocumentService::class)->hasActiveCv($user),
        ]);
    }

    public function grant(Request $request): RedirectResponse
    {
        $version = CandidatePoolConsentVersionService::activeVersion();
        abort_if($version === null, 503);

        $user = $request->user();
        $status = $user->candidatePoolPreference?->current_status;

        if (in_array($status?->value, ['withdrawn', 'granted'], true)) {
            $this->consentService->regrant($user, $version, CandidatePoolConsentSource::PrivacySettings, $request);
        } else {
            $this->consentService->grant($user, $version, CandidatePoolConsentSource::PrivacySettings, $request);
        }

        return back()->with('success', 'تم تسجيل انضمامك إلى قاعدة المرشحين.');
    }

    public function withdraw(Request $request): RedirectResponse
    {
        $this->consentService->withdraw($request->user(), CandidatePoolConsentSource::PrivacySettings, $request);

        return back()->with('success', 'تم سحب موافقتك.');
    }
}
