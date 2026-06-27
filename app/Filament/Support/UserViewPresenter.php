<?php

namespace App\Filament\Support;

use App\Enums\RegistrationStatus;
use App\Models\User;
use App\Services\Portal\CompetencyProfilePresenter;
use App\Support\UserAccountRoleForm;

final class UserViewPresenter
{
    /**
     * @return array{stats: array<int, array{label: string, value: string, icon: string}>, sections: array<int, array<string, mixed>>}
     */
    public static function present(User $user): array
    {
        $payload = CompetencyProfilePresenter::make($user);
        $profile = $payload['profile'];

        return [
            'stats' => self::stats($user, $payload),
            'sections' => array_values(array_filter([
                self::accountSection($user),
                self::profileSection($profile),
                self::competencySection($profile),
                self::cvSummarySection($profile),
                self::bioSection($profile),
            ])),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{label: string, value: string, icon: string}>
     */
    private static function stats(User $user, array $payload): array
    {
        return [
            [
                'label' => 'مسارات مكتملة',
                'value' => (string) $payload['completedPaths']->count(),
                'icon' => 'heroicon-o-map',
            ],
            [
                'label' => 'برامج مكتملة',
                'value' => (string) $payload['completedPrograms']->count(),
                'icon' => 'heroicon-o-academic-cap',
            ],
            [
                'label' => 'ساعات تطوع معتمدة',
                'value' => number_format((float) $payload['approvedVolunteerHours'], 1),
                'icon' => 'heroicon-o-heart',
            ],
            [
                'label' => 'الشهادات',
                'value' => (string) $payload['platformCertificates']->count(),
                'icon' => 'heroicon-o-document-check',
            ],
        ];
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>}
     */
    private static function accountSection(User $user): array
    {
        $rows = [
            EntityViewPresenterSupport::row('الاسم', $user->name, 'heroicon-o-user'),
            EntityViewPresenterSupport::row('البريد الإلكتروني', $user->email ?? '—', 'heroicon-o-envelope'),
            EntityViewPresenterSupport::row('الجوال', filled($user->phone) ? $user->phone : '—', 'heroicon-o-phone'),
            EntityViewPresenterSupport::row(
                'الدور في المنصة',
                UserAccountRoleForm::tablePlatformRoleLabelAr($user),
                'heroicon-o-identification',
            ),
            EntityViewPresenterSupport::row(
                'حالة الحساب',
                $user->is_active ? 'نشط' : 'معطّل',
                'heroicon-o-signal',
                $user->is_active ? 'success' : 'danger',
            ),
            EntityViewPresenterSupport::row(
                'إشعارات البريد',
                $user->wantsEmailNotifications() ? 'مفعّلة' : 'معطّلة',
                'heroicon-o-bell',
                $user->wantsEmailNotifications() ? 'success' : 'gray',
            ),
            EntityViewPresenterSupport::row(
                'آخر دخول',
                EntityViewPresenterSupport::formatDateTime($user->last_login_at),
                'heroicon-o-clock',
            ),
            EntityViewPresenterSupport::row(
                'تاريخ إنشاء الحساب',
                EntityViewPresenterSupport::formatDateTime($user->created_at),
                'heroicon-o-calendar-days',
            ),
        ];

        if ($user->email_verified_at !== null) {
            $rows[] = EntityViewPresenterSupport::row(
                'تأكيد البريد',
                EntityViewPresenterSupport::formatDateTime($user->email_verified_at),
                'heroicon-o-check-badge',
                'success',
            );
        }

        return [
            'title' => 'معلومات الحساب',
            'icon' => 'heroicon-o-user-circle',
            'field' => 'account',
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>}|null
     */
    private static function profileSection(?\App\Models\Profile $profile): ?array
    {
        if ($profile === null) {
            return [
                'title' => 'الملف الشخصي',
                'icon' => 'heroicon-o-identification',
                'rows' => [
                    EntityViewPresenterSupport::row('الحالة', 'لم يُنشأ ملف كفاءات بعد', 'heroicon-o-information-circle', 'warning'),
                ],
            ];
        }

        $badges = implode('، ', $profile->displayMembershipBadges());

        return [
            'title' => 'الملف الشخصي',
            'icon' => 'heroicon-o-identification',
            'rows' => array_values(array_filter([
                EntityViewPresenterSupport::row('نوع العضوية', $badges !== '' ? $badges : '—', 'heroicon-o-tag'),
                filled($profile->job_title)
                    ? EntityViewPresenterSupport::row('المسمى الوظيفي', (string) $profile->job_title, 'heroicon-o-briefcase')
                    : null,
                filled($profile->city)
                    ? EntityViewPresenterSupport::row('المدينة', (string) $profile->city, 'heroicon-o-map-pin')
                    : null,
                $profile->birth_date !== null
                    ? EntityViewPresenterSupport::row('تاريخ الميلاد', EntityViewPresenterSupport::formatDate($profile->birth_date), 'heroicon-o-cake')
                    : null,
                filled($profile->gender)
                    ? EntityViewPresenterSupport::row('الجنس', self::genderLabel((string) $profile->gender), 'heroicon-o-user')
                    : null,
                filled($profile->iconic_skill)
                    ? EntityViewPresenterSupport::row('المهارة المميزة', (string) $profile->iconic_skill, 'heroicon-o-sparkles')
                    : null,
                EntityViewPresenterSupport::row(
                    'لغة السيرة',
                    $profile->cv_language === 'en' ? 'الإنجليزية' : 'العربية',
                    'heroicon-o-language',
                ),
            ])),
        ];
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>}|null
     */
    private static function competencySection(?\App\Models\Profile $profile): ?array
    {
        if ($profile === null) {
            return null;
        }

        $cards = $profile->presentCompetencyCards();

        if ($cards === []) {
            return [
                'title' => 'مستويات الكفاءات',
                'icon' => 'heroicon-o-chart-bar',
                'rows' => [
                    EntityViewPresenterSupport::row('الحالة', 'لم تُدخل مستويات الكفاءات بعد', 'heroicon-o-information-circle', 'gray'),
                ],
            ];
        }

        $rows = [];
        foreach ($cards as $card) {
            $rows[] = EntityViewPresenterSupport::row(
                (string) $card['title'],
                (string) $card['level'],
                'heroicon-o-academic-cap',
            );
        }

        return [
            'title' => 'مستويات الكفاءات',
            'icon' => 'heroicon-o-chart-bar',
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>}|null
     */
    private static function cvSummarySection(?\App\Models\Profile $profile): ?array
    {
        if ($profile === null) {
            return null;
        }

        $skills = $profile->cvSkillsStructured();
        $languages = $profile->cvLanguagesStructured();
        $education = is_array($profile->cv_sections['education'] ?? null) ? $profile->cv_sections['education'] : [];
        $experience = is_array($profile->cv_sections['experience'] ?? null) ? $profile->cv_sections['experience'] : [];
        $courses = is_array($profile->cv_sections['courses'] ?? null) ? $profile->cv_sections['courses'] : [];

        $skillPreview = $skills === []
            ? '—'
            : implode('، ', array_slice(array_map(fn (array $s): string => $s['skill_name'], $skills), 0, 5))
                .(count($skills) > 5 ? ' …' : '');

        $languagePreview = $languages === []
            ? '—'
            : implode('، ', array_map(fn (array $l): string => $l['language_name'], $languages));

        return [
            'title' => 'ملخص السيرة الذاتية',
            'icon' => 'heroicon-o-document-text',
            'rows' => [
                EntityViewPresenterSupport::row('المهارات', $skillPreview, 'heroicon-o-wrench-screwdriver'),
                EntityViewPresenterSupport::row('اللغات', $languagePreview, 'heroicon-o-language'),
                EntityViewPresenterSupport::row('عدد الخبرات', (string) count($experience), 'heroicon-o-building-office'),
                EntityViewPresenterSupport::row('عدد المؤهلات', (string) count($education), 'heroicon-o-book-open'),
                EntityViewPresenterSupport::row('عدد الدورات', (string) count($courses), 'heroicon-o-rectangle-stack'),
            ],
        ];
    }

    /**
     * @return array{title: string, icon: string, prose: string}|null
     */
    private static function bioSection(?\App\Models\Profile $profile): ?array
    {
        if ($profile === null || blank($profile->bio)) {
            return null;
        }

        return EntityViewPresenterSupport::proseSection(
            'نبذة عن المستفيد',
            'heroicon-o-chat-bubble-left-right',
            (string) $profile->bio,
        );
    }

    private static function genderLabel(string $gender): string
    {
        return match ($gender) {
            'male' => 'ذكر',
            'female' => 'أنثى',
            default => $gender,
        };
    }
}
