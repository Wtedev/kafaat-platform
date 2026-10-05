<?php

namespace App\Http\Controllers\StaffUi;

use App\Http\Controllers\Controller;
use App\Models\PendingEmailChange;
use App\Models\User;
use App\Services\Auth\AccountPasswordChangeService;
use App\Services\Auth\EmailChangeService;
use App\Services\Media\PublicMediaLifecycleService;
use App\Services\Staff\StaffProfileUpdateService;
use App\Support\Privacy\SensitiveContactMasker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class StaffProfileController extends Controller
{
    public function show(Request $request, EmailChangeService $emailChanges): View
    {
        $user = $this->actor($request);
        $pending = $emailChanges->pendingFor($user);

        $photo = is_string($user->staff_photo) && $user->staff_photo !== '' && Storage::disk('public')->exists($user->staff_photo)
            ? Storage::disk('public')->url($user->staff_photo)
            : null;

        return view('staff-ui.profile', [
            'staffName' => $user->name,
            'staffEmail' => $user->email,
            'user' => $user,
            'photoUrl' => $photo,
            'pendingEmail' => $pending instanceof PendingEmailChange
                ? (string) SensitiveContactMasker::maskEmail($pending->pending_email)
                : null,
        ]);
    }

    public function update(
        Request $request,
        StaffProfileUpdateService $profiles,
        PublicMediaLifecycleService $media,
    ): RedirectResponse {
        $user = $this->actor($request);

        $request->validate([
            'staff_photo' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
                'dimensions:max_width=4000,max_height=4000',
            ],
        ], [
            'staff_photo.image' => 'يجب أن يكون الملف صورة حقيقية (JPEG أو PNG أو WebP).',
            'staff_photo.mimes' => 'الصيغ المسموحة فقط: JPEG و PNG و WebP. لا يُسمح بـ SVG أو GIF.',
            'staff_photo.max' => 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت.',
            'staff_photo.dimensions' => 'أبعاد الصورة كبيرة جداً. الحد الأقصى 4000×4000 بكسل.',
        ]);

        $uploaded = null;
        $photoPath = $user->staff_photo;
        if ($request->hasFile('staff_photo')) {
            $uploaded = $media->storeUpload($request->file('staff_photo'), 'staff-photos');
            $photoPath = $uploaded;
        } elseif ($request->boolean('remove_staff_photo')) {
            $photoPath = null;
        }

        try {
            $profiles->update($user, [
                'name' => $request->input('name'),
                'phone' => $request->input('phone'),
                'staff_photo' => $photoPath,
                'notify_email' => $request->boolean('notify_email'),
            ]);
        } catch (Throwable $e) {
            if (is_string($uploaded)) {
                $media->discardFailedUpload($uploaded);
            }

            throw $e;
        }

        return redirect()
            ->route('staff-ui.profile')
            ->with('status', 'تم حفظ الملف الشخصي');
    }

    public function requestEmailChange(Request $request, EmailChangeService $emailChanges): RedirectResponse
    {
        $user = $this->actor($request);
        $result = $emailChanges->start(
            $user,
            trim((string) $request->input('new_email', '')),
            trim((string) $request->input('new_email_confirmation', '')),
        );

        if (! $result['ok']) {
            $field = $result['field'] ?? 'email';

            throw ValidationException::withMessages([
                $field === 'email_confirmation' ? 'new_email_confirmation' : 'new_email' => $result['message'],
            ]);
        }

        return redirect()
            ->route('staff-ui.profile')
            ->with('status', 'تم إرسال رمز التحقق');
    }

    public function verifyEmailChange(Request $request, EmailChangeService $emailChanges): RedirectResponse
    {
        $user = $this->actor($request);
        $result = $emailChanges->verify($user, trim((string) $request->input('email_otp', '')));

        if (! $result['ok']) {
            throw ValidationException::withMessages([
                'email_otp' => $result['message'],
            ]);
        }

        return redirect()
            ->route('staff-ui.profile')
            ->with('status', EmailChangeService::MSG_SUCCESS);
    }

    public function resendEmailChange(Request $request, EmailChangeService $emailChanges): RedirectResponse
    {
        $user = $this->actor($request);
        $result = $emailChanges->resend($user);

        if (! $result['ok']) {
            throw ValidationException::withMessages([
                'email_otp' => $result['message'],
            ]);
        }

        return redirect()
            ->route('staff-ui.profile')
            ->with('status', 'تم إرسال رمز جديد');
    }

    public function cancelEmailChange(Request $request, EmailChangeService $emailChanges): RedirectResponse
    {
        $emailChanges->cancel($this->actor($request));

        return redirect()
            ->route('staff-ui.profile')
            ->with('status', 'تم إلغاء طلب تغيير البريد');
    }

    public function changePassword(Request $request, AccountPasswordChangeService $passwords): RedirectResponse
    {
        $user = $this->actor($request);
        $current = trim((string) $request->input('current_password', ''));
        $password = trim((string) $request->input('password', ''));
        $confirmation = trim((string) $request->input('password_confirmation', ''));

        if ($current === '' && $password === '' && $confirmation === '') {
            return redirect()->route('staff-ui.profile');
        }

        Validator::make(
            [
                'current_password' => $current,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ],
            [
                'current_password' => ['required', 'string'],
                'password' => AccountPasswordChangeService::newPasswordRules(),
            ],
            [],
            [
                'current_password' => 'كلمة المرور الحالية',
                'password' => 'كلمة المرور الجديدة',
                'password_confirmation' => 'تأكيد كلمة المرور',
            ],
        )->validate();

        $passwords->change($user, $current, $password, session()->getId());

        return redirect()
            ->route('staff-ui.profile')
            ->with('status', AccountPasswordChangeService::MSG_SUCCESS);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canAccessFilamentAdmin(), 403);

        return $user;
    }
}
