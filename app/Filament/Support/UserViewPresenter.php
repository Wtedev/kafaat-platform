<?php

namespace App\Filament\Support;

use App\Enums\ProfileGender;
use App\Enums\RegistrationStatus;
use App\Filament\Resources\UserResource;
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
        $editableSections = UserInlineEditSupport::editableSectionKeys();

        return [
            'stats' => self::stats($user, $payload),
            'sections' => array_values(array_filter([
                self::accountSection($user, $editableSections),
                self::profileSection($profile, $editableSections),
                self::competencySection($profile, $editableSections),
                $user->isPortalUser() ? self::cvSummarySection($user, $profile) : null,
                self::bioSection($profile, $editableSections),
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
     * @param  list<string>  $editableSections
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>, field?: string}
     */
    private static function accountSection(User $user, array $editableSections): array
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

        return self::section(
            title: 'معلومات الحساب',
            icon: 'heroicon-o-user-circle',
            rows: $rows,
            field: in_array('account', $editableSections, true) ? 'account' : null,
        );
    }

    /**
     * @param  list<string>  $editableSections
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>, field?: string}|null
     */
    private static function profileSection(?\App\Models\Profile $profile, array $editableSections): ?array
    {
        $field = in_array('profile', $editableSections, true) ? 'profile' : null;

        if ($profile === null) {
            return self::section(
                title: 'الملف الشخصي',
                icon: 'heroicon-o-identification',
                rows: [
                    EntityViewPresenterSupport::row('الحالة', 'لم يُنشأ ملف كفاءات بعد', 'heroicon-o-information-circle', 'warning'),
                ],
                field: $field,
            );
        }

        $badges = implode('، ', $profile->displayMembershipBadges());

        return self::section(
            title: 'الملف الشخصي',
            icon: 'heroicon-o-identification',
            rows: array_values(array_filter([
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
                    ? EntityViewPresenterSupport::row(
                        'الجنس',
                        $profile->gender instanceof ProfileGender
                            ? $profile->gender->label()
                            : self::genderLabel((string) $profile->gender),
                        'heroicon-o-user',
                    )
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
            field: $field,
        );
    }

    /**
     * @param  list<string>  $editableSections
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>, field?: string}|null
     */
    private static function competencySection(?\App\Models\Profile $profile, array $editableSections): ?array
    {
        $field = in_array('competency', $editableSections, true) ? 'competency' : null;

        if ($profile === null) {
            if ($field === null) {
                return null;
            }

            return self::section(
                title: 'مستويات الكفاءات',
                icon: 'heroicon-o-chart-bar',
                rows: [
                    EntityViewPresenterSupport::row('الحالة', 'لم يُنشأ ملف كفاءات بعد', 'heroicon-o-information-circle', 'warning'),
                ],
                field: $field,
            );
        }

        $cards = $profile->presentCompetencyCards();

        if ($cards === []) {
            return self::section(
                title: 'مستويات الكفاءات',
                icon: 'heroicon-o-chart-bar',
                rows: [
                    EntityViewPresenterSupport::row('الحالة', 'لم تُدخل مستويات الكفاءات بعد', 'heroicon-o-information-circle', 'gray'),
                ],
                field: $field,
            );
        }

        $rows = [];
        foreach ($cards as $card) {
            $rows[] = EntityViewPresenterSupport::row(
                (string) $card['title'],
                (string) $card['level'],
                'heroicon-o-academic-cap',
            );
        }

        return self::section(
            title: 'مستويات الكفاءات',
            icon: 'heroicon-o-chart-bar',
            rows: $rows,
            field: $field,
        );
    }

    /**
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>, header_actions?: array<int, array<string, string>>}
     */
    private static function cvSummarySection(User $user, ?\App\Models\Profile $profile): array
    {
        if ($profile === null) {
            $rows = [
                EntityViewPresenterSupport::row('الحالة', 'لم يُنشأ ملف كفاءات بعد', 'heroicon-o-information-circle', 'warning'),
            ];
        } else {
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

            $rows = [
                EntityViewPresenterSupport::row('المهارات', $skillPreview, 'heroicon-o-wrench-screwdriver'),
                EntityViewPresenterSupport::row('اللغات', $languagePreview, 'heroicon-o-language'),
                EntityViewPresenterSupport::row('عدد الخبرات', (string) count($experience), 'heroicon-o-building-office'),
                EntityViewPresenterSupport::row('عدد المؤهلات', (string) count($education), 'heroicon-o-book-open'),
                EntityViewPresenterSupport::row('عدد الدورات', (string) count($courses), 'heroicon-o-rectangle-stack'),
            ];
        }

        $section = self::section(
            title: 'ملخص السيرة الذاتية',
            icon: 'heroicon-o-document-text',
            rows: $rows,
        );

        $section['header_actions'] = [[
            'label' => 'تحميل السيرة الذاتية',
            'url' => UserResource::beneficiaryCvPdfUrl($user),
            'icon' => 'heroicon-o-arrow-down-tray',
        ]];

        return $section;
    }

    /**
     * @param  list<string>  $editableSections
     * @return array{title: string, icon: string, prose: string, field?: string}|null
     */
    private static function bioSection(?\App\Models\Profile $profile, array $editableSections): ?array
    {
        $field = in_array('bio', $editableSections, true) ? 'bio' : null;

        if ($profile === null) {
            if ($field === null) {
                return null;
            }

            return EntityViewPresenterSupport::proseSection(
                'نبذة عن المستفيد',
                'heroicon-o-chat-bubble-left-right',
                'لم يُنشأ ملف كفاءات بعد.',
                $field,
            );
        }

        if (blank($profile->bio) && $field === null) {
            return null;
        }

        return EntityViewPresenterSupport::proseSection(
            'نبذة عن المستفيد',
            'heroicon-o-chat-bubble-left-right',
            filled($profile->bio) ? (string) $profile->bio : 'لم تُضف نبذة بعد.',
            $field,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{title: string, icon: string, rows: array<int, array<string, mixed>>, field?: string}
     */
    private static function section(string $title, string $icon, array $rows, ?string $field = null): array
    {
        $section = [
            'title' => $title,
            'icon' => $icon,
            'rows' => $rows,
        ];

        if ($field !== null) {
            $section['field'] = $field;
        }

        return $section;
    }

    private static function genderLabel(string $gender): string
    {
        return ProfileGender::tryFrom($gender)?->label() ?? $gender;
    }
}
