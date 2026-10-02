<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Staff\BeneficiaryCvPdfDownload;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BeneficiaryCvPdfController extends Controller
{
    public function __invoke(Request $request, User $user): Response
    {
        $this->authorize('downloadCv', $user);

        return app(BeneficiaryCvPdfDownload::class)->download($user);
    }
}
