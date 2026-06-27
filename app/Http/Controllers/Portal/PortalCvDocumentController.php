<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Documents\CvDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalCvDocumentController extends Controller
{
    public function __construct(
        private readonly CvDocumentService $cvDocuments,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'cv' => ['required', 'file'],
        ]);

        try {
            $this->cvDocuments->upload($request->user(), $request->file('cv'), $request->user(), $request);
        } catch (InvalidArgumentException $exception) {
            return $this->validationErrorResponse($exception);
        }

        return back()->with('success', 'تم رفع ملف السيرة الذاتية بنجاح.');
    }

    public function download(Request $request): StreamedResponse|RedirectResponse
    {
        $user = $request->user();
        $document = $this->cvDocuments->currentCv($user);

        if ($document === null) {
            abort(404);
        }

        try {
            return $this->cvDocuments->downloadResponse($user, $document, $user, $request);
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        if (! Hash::check((string) $request->input('password'), (string) $request->user()->password)) {
            return back()->withErrors(['password' => 'كلمة المرور غير صحيحة.']);
        }

        try {
            $this->cvDocuments->delete($request->user(), $request->user(), $request);
        } catch (InvalidArgumentException $exception) {
            if ($exception->getMessage() === 'cv_not_found') {
                return back()->withErrors(['cv' => 'لا توجد سيرة ذاتية لحذفها.']);
            }

            throw $exception;
        }

        return back()->with('success', 'تم حذف ملف السيرة الذاتية.');
    }

    private function validationErrorResponse(InvalidArgumentException $exception): RedirectResponse
    {
        $message = match ($exception->getMessage()) {
            'cv_file_too_large' => 'حجم الملف أكبر من الحد المسموح.',
            'cv_invalid_extension', 'cv_invalid_mime', 'cv_invalid_pdf', 'cv_double_extension' => 'نوع الملف غير مسموح. يُقبل PDF فقط.',
            default => 'تعذر رفع الملف. يرجى المحاولة مرة أخرى.',
        };

        return back()->withErrors(['cv' => $message]);
    }
}
