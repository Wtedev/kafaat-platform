<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Portal\CompetencyMpdfExporter;
use App\Services\Portal\CompetencyProfilePresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class BeneficiaryCvPdfController extends Controller
{
    public function __invoke(Request $request, User $user): Response
    {
        $this->authorize('view', $user);

        abort_unless($user->isPortalUser(), 404);

        $data = CompetencyProfilePresenter::make($user);

        $name = trim((string) ($user->name ?: 'User'));
        $name = (string) preg_replace('/[\\\\\\/:*?"<>|]+/u', '', $name);
        $name = trim((string) preg_replace('/\\s+/u', ' ', $name));
        $filename = 'Kaffah CV '.$name.'.pdf';
        $asciiFallback = 'Kaffah-CV-'.Str::slug(Str::ascii($user->name ?: 'user')).'.pdf';

        return (new CompetencyMpdfExporter)->stream($data, $filename, $asciiFallback, 'attachment');
    }
}
