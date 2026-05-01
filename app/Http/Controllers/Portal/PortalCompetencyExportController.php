<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Portal\CompetencyMpdfExporter;
use App\Services\Portal\CompetencyProfilePresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PortalCompetencyExportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $data = CompetencyProfilePresenter::make($user);

        $name = trim((string) ($user->name ?: 'User'));
        $name = (string) preg_replace('/[\\\\\\/:*?"<>|]+/u', '', $name);
        $name = trim((string) preg_replace('/\\s+/u', ' ', $name));
        $filename = 'Kaffah CV '.$name.'.pdf';
        $asciiFallback = 'Kaffah-CV-'.Str::slug(Str::ascii($user->name ?: 'user')).'.pdf';

        return (new CompetencyMpdfExporter)->stream($data, $filename, $asciiFallback);
    }
}
