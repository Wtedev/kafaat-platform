<?php

namespace App\Services;

use App\Enums\IdentityType;
use App\Enums\ProfileGender;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Support\ProgramAcceptanceConditions;
use Illuminate\Support\Carbon;

final class ProgramAcceptanceConditionEvaluator
{
    /**
     * @return array{eligible: bool, reasons: list<string>}
     */
    public function evaluate(TrainingProgram $program, User $user): array
    {
        $conditions = ProgramAcceptanceConditions::normalize(
            is_array($program->acceptance_conditions) ? $program->acceptance_conditions : null
        );

        if ($conditions === null) {
            return ['eligible' => true, 'reasons' => []];
        }

        $user->loadMissing('profile');
        $reasons = [];

        if ($conditions['require_complete_profile'] && ! $user->hasCompletedRequiredIdentityData()) {
            $reasons[] = 'يلزم إكمال بيانات الملف الشخصي (الاسم، الهوية، الجوال، تاريخ الميلاد، والجنس) قبل التسجيل.';
        }

        if ($conditions['require_saudi_national']) {
            if ($user->identity_type !== IdentityType::NationalId) {
                $reasons[] = 'هذا البرنامج مخصص لمن لديهم هوية وطنية (سعودي الجنسية)، وليست إقامة.';
            }
        }

        if ($conditions['genders'] !== []) {
            $gender = $user->profile?->gender;
            $genderValue = $gender instanceof ProfileGender ? $gender->value : null;
            if ($genderValue === null || ! in_array($genderValue, $conditions['genders'], true)) {
                $labels = collect($conditions['genders'])
                    ->map(static fn (string $v): string => ProfileGender::tryFrom($v)?->label() ?? $v)
                    ->implode(' / ');
                $reasons[] = 'هذا البرنامج مخصص لـ: '.$labels.'.';
            }
        }

        $needsAge = $conditions['min_age'] !== null || $conditions['max_age'] !== null;
        if ($needsAge) {
            $birthDate = $user->profile?->birth_date;
            if ($birthDate === null) {
                $reasons[] = 'يلزم توفر تاريخ الميلاد في ملفك للتحقق من شرط العمر.';
            } else {
                $age = Carbon::parse($birthDate)->age;
                if ($conditions['min_age'] !== null && $age < $conditions['min_age']) {
                    $reasons[] = 'الحد الأدنى للعمر في هذا البرنامج هو '.$conditions['min_age'].' سنة.';
                }
                if ($conditions['max_age'] !== null && $age > $conditions['max_age']) {
                    $reasons[] = 'الحد الأقصى للعمر في هذا البرنامج هو '.$conditions['max_age'].' سنة.';
                }
            }
        }

        if ($conditions['cities'] !== []) {
            $city = trim((string) ($user->profile?->city ?? ''));
            if ($city === '') {
                $reasons[] = 'يلزم تحديد مدينة الإقامة في ملفك الشخصي للتحقق من شرط المدينة.';
            } elseif (! ProgramAcceptanceConditions::citiesMatch($city, $conditions['cities'])) {
                $reasons[] = 'مدينة إقامتك غير مطابقة للمدن المسموح بها في هذا البرنامج ('.implode('، ', $conditions['cities']).').';
            }
        }

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    public function isEligible(TrainingProgram $program, User $user): bool
    {
        return $this->evaluate($program, $user)['eligible'];
    }
}
