<?php

namespace App\Services\StaffUi;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\StaffEmailChangedByAdminNotification;
use App\Services\Audit\StaffAccountAudit;
use App\Services\Identity\PersonNameService;
use App\Support\Auth\EmailNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

final class StaffMemberProfile
{
    /**
     * @return list<string>
     */
    public function update(User $actor, User $target, Request $request): array
    {
        $parts = $this->validatedName($request);
        $nextName = [
            ...$parts,
            'name' => PersonNameService::buildFullName($parts),
        ];
        $changed = $this->changedKeys($target, $nextName);

        $email = null;
        if (! $actor->is($target)) {
            $email = $this->validatedEmail($request);
            if (strcasecmp((string) $target->email, $email) !== 0) {
                $this->assertEmailAvailable($target, $email);
                $changed[] = 'email';
            } else {
                $email = null;
            }
        } elseif ($request->exists('email') && ! EmailNormalizer::equals((string) $request->input('email'), (string) $target->email)) {
            abort(403);
        }

        $target->fill($nextName)->save();

        if ($email !== null) {
            $oldEmail = (string) $target->email;
            $this->endSessions($target, $oldEmail);
            $target->email = $email;
            $target->email_verified_at = null;
            $target->save();

            $notice = new StaffEmailChangedByAdminNotification($oldEmail, $email);
            Notification::route('mail', $oldEmail)->notify($notice);
            Notification::route('mail', $email)->notify($notice);
        }

        StaffAccountAudit::recordUpdate($actor, $target, $changed, $request);

        return $changed;
    }

    public function sendPasswordReset(User $actor, User $target, Request $request): void
    {
        $status = Password::broker()->sendResetLink(['email' => $target->email]);

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'password_reset' => 'انتظر دقيقة قبل إرسال رابط جديد.',
            ]);
        }

        if ($status !== Password::RESET_LINK_SENT) {
            abort(422);
        }

        StaffAccountAudit::recordPasswordReset($actor, $target, $request);
    }

    /**
     * @return array<string, string>
     */
    private function validatedName(Request $request): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'father_name' => ['required', 'string', 'max:100'],
            'grandfather_name' => ['required', 'string', 'max:100'],
            'family_name' => ['required', 'string', 'max:100'],
        ], [
            'first_name.required' => 'الاسم مطلوب.',
            'father_name.required' => 'اسم الأب مطلوب.',
            'grandfather_name.required' => 'اسم الجد مطلوب.',
            'family_name.required' => 'اسم العائلة مطلوب.',
        ]);

        try {
            return PersonNameService::normalizedParts($data);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages([
                'first_name' => 'أدخل الاسم الرباعي بأحرف عربية أو إنجليزية.',
            ]);
        }
    }

    private function validatedEmail(Request $request): string
    {
        $email = EmailNormalizer::normalize((string) $request->input('email'));

        validator(
            ['email' => $email],
            ['email' => ['required', 'string', 'email', 'max:255']],
            [
                'email.required' => 'البريد الإلكتروني مطلوب.',
                'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            ],
        )->validate();

        return $email;
    }

    private function assertEmailAvailable(User $target, string $email): void
    {
        $duplicate = User::query()
            ->whereEmailIgnoreCase($email)
            ->whereKeyNot($target->getKey())
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'email' => 'البريد الإلكتروني مستخدم بالفعل.',
            ]);
        }
    }

    private function endSessions(User $target, string $oldEmail): void
    {
        DB::table('sessions')->where('user_id', $target->id)->delete();
        EmailVerificationCode::query()->where('user_id', $target->id)->delete();
        DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();
    }

    /**
     * @param  array<string, mixed>  $next
     * @return list<string>
     */
    private function changedKeys(User $current, array $next): array
    {
        $changed = [];

        foreach ($next as $key => $value) {
            if ((string) ($current->{$key} ?? '') !== (string) ($value ?? '')) {
                $changed[] = $key;
            }
        }

        return $changed;
    }
}
