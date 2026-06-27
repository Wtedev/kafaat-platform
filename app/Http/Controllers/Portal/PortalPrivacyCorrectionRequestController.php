<?php

namespace App\Http\Controllers\Portal;

use App\Enums\IdentityType;
use App\Enums\PrivacyCorrectionFieldCode;
use App\Http\Controllers\Controller;
use App\Services\Privacy\PrivacyRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PortalPrivacyCorrectionRequestController extends Controller
{
    public function __construct(
        private readonly PrivacyRequestService $privacyRequestService,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $fieldCode = PrivacyCorrectionFieldCode::tryFrom((string) $request->input('field_code'));
        if ($fieldCode === null) {
            throw ValidationException::withMessages(['field_code' => 'نوع الحقل غير مدعوم.']);
        }

        $user = $request->user();

        if ($fieldCode->isSelfServiceFor($user)) {
            $route = $fieldCode->selfServiceRoute();

            return redirect()
                ->route($route ?? 'portal.profile')
                ->with('error', 'يمكنك تعديل هذا الحقل مباشرة من ملفك الشخصي.');
        }

        $rules = [
            'field_code' => ['required', Rule::enum(PrivacyCorrectionFieldCode::class)],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'password' => $fieldCode->requiresSensitiveVerification()
                ? ['required', 'string']
                : ['nullable', 'string'],
        ];

        if ($fieldCode === PrivacyCorrectionFieldCode::StructuredName) {
            $rules['first_name'] = ['required', 'string', 'max:100'];
            $rules['father_name'] = ['required', 'string', 'max:100'];
            $rules['grandfather_name'] = ['required', 'string', 'max:100'];
            $rules['family_name'] = ['required', 'string', 'max:100'];
        } elseif ($fieldCode === PrivacyCorrectionFieldCode::BirthDate) {
            $rules['birth_date'] = ['required', 'date', 'before_or_equal:today'];
        } elseif ($fieldCode === PrivacyCorrectionFieldCode::Email) {
            $rules['email'] = ['required', 'email', 'max:255'];
        } elseif ($fieldCode === PrivacyCorrectionFieldCode::IdentityNumber) {
            $rules['identity_type'] = ['required', Rule::enum(IdentityType::class)];
            $rules['identity_number'] = ['required', 'string', 'max:20'];
        }

        $validated = $request->validate($rules);

        try {
            $privacyRequest = $this->privacyRequestService->submitDataCorrection(
                $user,
                $fieldCode,
                $validated['reason'],
                $validated,
                $request,
                $validated['password'] ?? null,
            );
        } catch (\InvalidArgumentException $exception) {
            if ($exception->getMessage() === 'duplicate_identity') {
                throw ValidationException::withMessages([
                    'identity_number' => 'رقم الهوية مستخدم مسبقاً.',
                ]);
            }

            throw $exception;
        }

        return redirect()
            ->route('portal.privacy')
            ->with('success', 'تم تقديم طلب التصحيح. المرجع: '.$privacyRequest->uuid);
    }
}
