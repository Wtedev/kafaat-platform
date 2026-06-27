<?php

namespace App\Http\Controllers;

use App\Models\PrivacyPolicyVersion;
use App\Services\Privacy\PrivacyPolicyHtmlSanitizer;
use App\Services\Privacy\PrivacyPolicyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PublicPrivacyPolicyController extends Controller
{
    public function current(): View
    {
        $policy = PrivacyPolicyService::active();

        if ($policy === null) {
            Log::warning('privacy_policy.unavailable', ['route' => 'public.privacy']);

            return view('public.privacy-unavailable');
        }

        return view('public.privacy', [
            'policy' => $policy,
            'sanitizedContent' => PrivacyPolicyHtmlSanitizer::sanitize($policy->content),
        ]);
    }

    public function version(string $version): View
    {
        $policy = PrivacyPolicyService::findPublishedByVersion($version);

        if ($policy === null) {
            abort(404);
        }

        return view('public.privacy', [
            'policy' => $policy,
            'sanitizedContent' => PrivacyPolicyHtmlSanitizer::sanitize($policy->content),
        ]);
    }
}
