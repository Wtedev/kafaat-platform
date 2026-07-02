<?php

namespace App\Http\Controllers\Portal;

use App\Enums\CandidatePoolConsentSource;
use App\Http\Controllers\Controller;
use App\Services\CandidatePool\CandidatePoolConsentService;
use App\Services\CandidatePool\CandidatePoolConsentVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PortalCompetencyEmploymentConsentController extends Controller
{
    public function __construct(
        private readonly CandidatePoolConsentService $consentService,
    ) {}

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employment_consent' => ['nullable', 'boolean'],
        ]);

        $version = CandidatePoolConsentVersionService::activeVersion();
        if ($version === null) {
            return back()->withErrors([
                'employment_consent' => 'إعدادات مشاركة البيانات غير متاحة حالياً.',
            ]);
        }

        $user = $request->user();
        $wantsConsent = $request->boolean('employment_consent');
        $status = $user->candidatePoolPreference?->current_status?->value;

        if ($wantsConsent) {
            if ($status === 'granted') {
                return back()->with('success', 'موافقتك على مشاركة البيانات لغرض التوظيف مسجّلة مسبقاً.');
            }

            if (in_array($status, ['withdrawn'], true)) {
                $this->consentService->regrant($user, $version, CandidatePoolConsentSource::CompetencyPage, $request);
            } else {
                $this->consentService->grant($user, $version, CandidatePoolConsentSource::CompetencyPage, $request);
            }

            return back()->with('success', 'تم تسجيل موافقتك على مشاركة بياناتك لغرض التوظيف.');
        }

        if ($status === 'granted') {
            $this->consentService->withdraw($user, CandidatePoolConsentSource::CompetencyPage, $request);

            return back()->with('success', 'تم سحب موافقتك على مشاركة البيانات لغرض التوظيف.');
        }

        return back()->with('success', 'لم يتم تسجيل موافقة على مشاركة البيانات.');
    }
}
