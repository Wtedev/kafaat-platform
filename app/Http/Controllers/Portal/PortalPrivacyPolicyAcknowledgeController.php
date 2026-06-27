<?php

namespace App\Http\Controllers\Portal;

use App\Enums\PrivacyPolicyAcknowledgementSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\AcknowledgePrivacyPolicyRequest;
use App\Services\Privacy\PrivacyPolicyAcknowledgementService;
use App\Services\Privacy\PrivacyPolicyHtmlSanitizer;
use App\Services\Privacy\PrivacyPolicyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PortalPrivacyPolicyAcknowledgeController extends Controller
{
    public function __construct(
        private readonly PrivacyPolicyAcknowledgementService $acknowledgementService,
    ) {}

    public function show(): View|RedirectResponse
    {
        $policy = PrivacyPolicyService::active();

        if ($policy === null) {
            Log::warning('privacy_policy.unavailable', ['route' => 'portal.privacy-policy.acknowledge']);

            return redirect()
                ->route('portal.dashboard')
                ->with('status', 'سياسة الخصوصية غير متاحة حالياً. يرجى المحاولة لاحقاً.');
        }

        $user = auth()->user();

        if (! $this->acknowledgementService->userNeedsAcknowledgement($user)) {
            return redirect()->route('portal.dashboard');
        }

        return view('portal.privacy-policy-acknowledge', [
            'policy' => $policy,
            'sanitizedContent' => PrivacyPolicyHtmlSanitizer::sanitize($policy->content),
            'acknowledgementText' => $this->acknowledgementService->acknowledgementText(),
        ]);
    }

    public function store(AcknowledgePrivacyPolicyRequest $request): RedirectResponse
    {
        $policy = PrivacyPolicyService::activeOrFail();

        $this->acknowledgementService->acknowledge(
            $request->user(),
            $policy,
            PrivacyPolicyAcknowledgementSource::PolicyUpdate,
            $request,
        );

        return redirect()
            ->route('portal.dashboard')
            ->with('status', 'تم تسجيل اطلاعك على سياسة الخصوصية.');
    }
}
