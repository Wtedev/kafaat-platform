<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Privacy\PrivacyPolicyHtmlSanitizer;
use App\Services\Privacy\PrivacyPolicyService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalSettingsController extends Controller
{
    public function index(Request $request): View
    {
        return view('portal.settings.index', [
            'user' => $request->user(),
        ]);
    }

    public function account(Request $request): View
    {
        return view('portal.settings.account', [
            'user' => $request->user(),
        ]);
    }

    public function profile(Request $request): View
    {
        $user = $request->user()->load('profile');

        return view('portal.settings.profile', compact('user'));
    }

    public function legal(): View
    {
        $policy = PrivacyPolicyService::active();

        return view('portal.settings.legal', [
            'policy' => $policy,
            'sanitizedContent' => $policy !== null
                ? PrivacyPolicyHtmlSanitizer::sanitize($policy->content)
                : null,
        ]);
    }
}
