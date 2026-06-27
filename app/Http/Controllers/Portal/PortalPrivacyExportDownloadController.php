<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PrivacyExportFile;
use App\Services\Privacy\Export\PrivacyExportDownloadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalPrivacyExportDownloadController extends Controller
{
    public function __construct(
        private readonly PrivacyExportDownloadService $downloadService,
    ) {}

    public function store(Request $request, PrivacyExportFile $privacyExportFile): StreamedResponse|RedirectResponse
    {
        $password = $request->input('password');

        try {
            return $this->downloadService->download(
                $request->user(),
                $privacyExportFile,
                $request,
                is_string($password) ? $password : null,
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            abort(403);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return redirect()
                ->route('portal.privacy')
                ->withErrors($exception->errors());
        }
    }
}
