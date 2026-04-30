<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PortalCertificateController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $certificates = $user->certificates()
            ->with('certificateable')
            ->latest('issued_at')
            ->paginate(15);

        return view('portal.certificates', compact('certificates'));
    }
}
