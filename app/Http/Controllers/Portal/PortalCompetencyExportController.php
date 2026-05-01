<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Portal\CompetencyProfilePresenter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PortalCompetencyExportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $data = CompetencyProfilePresenter::make($user);

        $pdf = Pdf::loadView('portal.competency-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true);

        $filename = 'kafaat-cv-'.Str::slug($user->name ?: 'user').'.pdf';

        return $pdf->stream($filename);
    }
}
