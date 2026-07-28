<?php

namespace App\Support\Exports;

use App\Enums\ProfileGender;
use App\Enums\RegistrationStatus;
use App\Models\ProgramRegistration;
use App\Models\User;
use App\Support\Privacy\SensitiveContactMasker;

/**
 * Allowlisted column catalog for program registrant Excel export.
 * Keys are UI/export identifiers only — never raw client DB column names.
 */
final class ProgramRegistrationExportColumns
{
    public const GROUP_ACCOUNT = 'account';

    public const GROUP_PROFILE = 'profile';

    public const GROUP_REGISTRATION = 'registration';

    /**
     * @return array<string, string> key => Arabic label (flat, for CheckboxList)
     */
    public static function optionLabels(?User $actor = null): array
    {
        $out = [];
        foreach (self::definitions($actor) as $key => $def) {
            $out[$key] = $def['label'];
        }

        return $out;
    }

    /**
     * @return array<string, array<string, string>> group => [key => label]
     */
    public static function groupedOptionLabels(?User $actor = null): array
    {
        $groups = [
            self::GROUP_ACCOUNT => [],
            self::GROUP_PROFILE => [],
            self::GROUP_REGISTRATION => [],
        ];

        foreach (self::definitions($actor) as $key => $def) {
            $groups[$def['group']][$key] = $def['label'];
        }

        return array_filter($groups, fn (array $opts): bool => $opts !== []);
    }

    /**
     * @return array<string, string> group key => Arabic group title
     */
    public static function groupTitles(): array
    {
        return [
            self::GROUP_ACCOUNT => 'بيانات الحساب',
            self::GROUP_PROFILE => 'الملف الشخصي',
            self::GROUP_REGISTRATION => 'بيانات التسجيل',
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultKeys(?User $actor = null): array
    {
        return array_keys(array_filter(
            self::definitions($actor),
            fn (array $def): bool => $def['default'] ?? false,
        ));
    }

    /**
     * @return list<string>
     */
    public static function allowlistedKeys(?User $actor = null): array
    {
        return array_keys(self::definitions($actor));
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function labelsForKeys(array $keys, ?User $actor = null): array
    {
        $defs = self::definitions($actor);

        return array_map(
            fn (string $key): string => $defs[$key]['label'] ?? $key,
            $keys,
        );
    }

    /**
     * @return list<string> column keys that must be written as Excel text
     */
    public static function textColumnKeys(): array
    {
        return [
            'user_phone',
            'identity_masked',
            'user_email',
        ];
    }

    public static function resolve(ProgramRegistration $registration, string $key, ?User $actor = null): mixed
    {
        $registration->loadMissing(['user.profile', 'approvedBy']);
        $user = $registration->user;
        $profile = $user?->profile;
        // Training registrant export is operational: exports.training unlocks contact cells.
        // Identity remains masked-only (no full national ID column — project privacy policy).
        $canContact = ($actor?->can('exports.training') ?? false)
            || self::actorCanSeeContact($actor);

        $raw = match ($key) {
            'registration_id' => $registration->id,
            'user_name' => $user?->name,
            'user_email' => $canContact
                ? $user?->email
                : SensitiveContactMasker::maskEmail($user?->email),
            'user_phone' => $canContact
                ? $user?->phone
                : SensitiveContactMasker::maskPhone($user?->phone),
            'identity_masked' => $user?->maskedIdentityNumber(),
            'gender' => self::genderLabel($profile?->gender),
            'birth_date' => $profile?->birth_date?->format('Y-m-d'),
            'city' => $profile?->city,
            'job_title' => $profile?->job_title,
            'status' => $registration->status instanceof RegistrationStatus
                ? $registration->status->label()
                : (string) $registration->status,
            'registered_at' => $registration->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'approved_at' => $registration->approved_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'approved_by_name' => $registration->approvedBy?->name,
            'rejected_reason' => $registration->rejected_reason,
            'attendance_percentage' => $registration->effectiveAttendancePercentage(),
            'score' => $registration->score,
            default => null,
        };

        return ExcelFormulaInjectionGuard::sanitize(
            is_scalar($raw) || $raw === null ? $raw : (string) $raw
        );
    }

    public static function actorCanSeeContact(?User $actor): bool
    {
        if ($actor === null) {
            return false;
        }

        return $actor->can('exports.beneficiaries.contact')
            || $actor->can('beneficiaries.view_contact');
    }

    public static function actorCanSeeMaskedIdentity(?User $actor): bool
    {
        if ($actor === null) {
            return false;
        }

        return $actor->can('beneficiaries.identity.view_masked')
            || $actor->can('beneficiaries.identity.view_full');
    }

    /**
     * @return array<string, array{label: string, group: string, default?: bool}>
     */
    private static function definitions(?User $actor = null): array
    {
        $defs = [
            'registration_id' => [
                'label' => 'رقم التسجيل',
                'group' => self::GROUP_REGISTRATION,
                'default' => false,
            ],
            'user_name' => [
                'label' => 'الاسم الكامل',
                'group' => self::GROUP_ACCOUNT,
                'default' => true,
            ],
            'user_email' => [
                'label' => 'البريد الإلكتروني',
                'group' => self::GROUP_ACCOUNT,
                'default' => true,
            ],
            'user_phone' => [
                'label' => 'رقم الجوال',
                'group' => self::GROUP_ACCOUNT,
                'default' => true,
            ],
            'gender' => [
                'label' => 'الجنس',
                'group' => self::GROUP_PROFILE,
                'default' => false,
            ],
            'birth_date' => [
                'label' => 'تاريخ الميلاد',
                'group' => self::GROUP_PROFILE,
                'default' => false,
            ],
            'city' => [
                'label' => 'المدينة',
                'group' => self::GROUP_PROFILE,
                'default' => false,
            ],
            'job_title' => [
                'label' => 'المسمى الوظيفي',
                'group' => self::GROUP_PROFILE,
                'default' => false,
            ],
            'status' => [
                'label' => 'حالة التسجيل',
                'group' => self::GROUP_REGISTRATION,
                'default' => true,
            ],
            'registered_at' => [
                'label' => 'تاريخ التسجيل',
                'group' => self::GROUP_REGISTRATION,
                'default' => true,
            ],
            'approved_at' => [
                'label' => 'تاريخ القبول',
                'group' => self::GROUP_REGISTRATION,
                'default' => false,
            ],
            'approved_by_name' => [
                'label' => 'قبل بواسطة',
                'group' => self::GROUP_REGISTRATION,
                'default' => false,
            ],
            'rejected_reason' => [
                'label' => 'سبب الرفض',
                'group' => self::GROUP_REGISTRATION,
                'default' => false,
            ],
            'attendance_percentage' => [
                'label' => 'نسبة الحضور',
                'group' => self::GROUP_REGISTRATION,
                'default' => false,
            ],
            'score' => [
                'label' => 'الدرجة',
                'group' => self::GROUP_REGISTRATION,
                'default' => false,
            ],
        ];

        if (self::actorCanSeeMaskedIdentity($actor)) {
            $defs['identity_masked'] = [
                'label' => 'رقم الهوية (مقنّع)',
                'group' => self::GROUP_ACCOUNT,
                'default' => false,
            ];
        }

        // Stable order: account → profile → registration
        $order = [
            'user_name', 'user_email', 'user_phone', 'identity_masked',
            'gender', 'birth_date', 'city', 'job_title',
            'registration_id', 'status', 'registered_at', 'approved_at',
            'approved_by_name', 'rejected_reason', 'attendance_percentage', 'score',
        ];

        $ordered = [];
        foreach ($order as $key) {
            if (isset($defs[$key])) {
                $ordered[$key] = $defs[$key];
            }
        }

        return $ordered;
    }

    private static function genderLabel(mixed $gender): ?string
    {
        if ($gender instanceof ProfileGender) {
            return $gender->label();
        }

        return match ((string) $gender) {
            'male' => 'ذكر',
            'female' => 'أنثى',
            default => null,
        };
    }
}
