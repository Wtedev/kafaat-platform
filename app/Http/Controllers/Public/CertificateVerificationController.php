<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;

class CertificateVerificationController extends Controller
{
    public function __invoke(Request $request, string $code)
    {
        $certificate = Certificate::with(['user', 'certificateable'])
            ->where('verification_code', $code)
            ->first();

        return view('public.certificate-verify', compact('certificate'));
    }
}
