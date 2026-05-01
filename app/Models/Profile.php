<?php

namespace App\Models;

use App\Enums\MembershipType;
use App\Services\Portal\CvFormOptions;
use App\Services\Portal\CvLanguagePresets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'membership_type' => 'beneficiary',
    ];

    protected $fillable = [
        'user_id',
        'membership_type',
        'gender',
        'birth_date',
        'city',
        'job_title',
        'bio',
        'avatar',
        'iconic_skill',
        'iconic_skill_style',
        'membership_badges',
        'competency_levels',
        'cv_sections',
        'cv_sections_visibility',
        'cv_language',
        'cv_path',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'competency_levels' => 'array',
            'cv_sections' => 'array',
            'cv_sections_visibility' => 'array',
            'membership_badges' => 'array',
            'membership_type' => MembershipType::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Profile $profile): void {
            if (is_array($profile->membership_badges)) {
                $profile->membership_badges = array_values(array_unique(array_intersect(
                    ['trainee', 'volunteer'],
                    $profile->membership_badges,
                )));
                if ($profile->membership_badges === []) {
                    $profile->membership_badges = null;
                }
            } else {
                $profile->membership_badges = null;
            }

            if (trim((string) ($profile->iconic_skill ?? '')) === '') {
                $profile->iconic_skill = null;
                $profile->iconic_skill_style = null;
            } else {
                $style = (string) ($profile->iconic_skill_style ?? '');
                if ($style === '' || ! in_array($style, self::allowedIconicSkillStyles(), true)) {
                    $profile->iconic_skill_style = 'amber';
                }
            }
        });
    }

    /**
     * @return list<string>
     */
    public static function allowedIconicSkillStyles(): array
    {
        return ['amber', 'emerald', 'sky', 'rose', 'violet', 'brand'];
    }

    /**
     * شارات نوع المستفيد للعرض: دائماً «مستفيد» ثم «متدرب» و/أو «متطوع» حسب membership_badges أو الرجوع إلى membership_type.
     *
     * @return list<string>
     */
    public function displayMembershipBadges(): array
    {
        $labels = ['مستفيد'];
        [$hasTrainee, $hasVolunteer] = $this->membershipBadgeFlags();

        if ($hasTrainee) {
            $labels[] = 'متدرب';
        }
        if ($hasVolunteer) {
            $labels[] = 'متطوع';
        }

        return $labels;
    }

    public function iconicSkillLabel(): string
    {
        $skill = trim((string) ($this->iconic_skill ?? ''));

        return $skill !== '' ? $skill : 'لا يوجد مهارة أيقونية';
    }

    /**
     * أنماط Tailwind آمنة لشارة المهارة الأيقونية (بدون CSS من المستخدم).
     */
    /**
     * تدرج خفيف لسطح شارة المهارة الأيقونية (يُدمج مع iconicSkillClasses).
     */
    public function iconicSkillGradientClasses(): string
    {
        $skill = trim((string) ($this->iconic_skill ?? ''));
        if ($skill === '') {
            return 'from-slate-50 via-white to-slate-100/90';
        }

        $style = $this->resolvedIconicSkillStyle();

        return match ($style) {
            'amber' => 'from-amber-50/95 via-amber-50/35 to-orange-50/70',
            'emerald' => 'from-emerald-50/95 via-emerald-50/30 to-teal-50/65',
            'sky' => 'from-sky-50/95 via-sky-50/35 to-cyan-50/60',
            'rose' => 'from-rose-50/95 via-rose-50/35 to-pink-50/60',
            'violet' => 'from-violet-50/95 via-violet-50/35 to-fuchsia-50/55',
            'brand' => 'from-[#f0f6fc] via-white to-[#e8f0f9]',
            default => 'from-amber-50/95 via-amber-50/35 to-orange-50/70',
        };
    }

    public function iconicSkillClasses(): string
    {
        $skill = trim((string) ($this->iconic_skill ?? ''));
        if ($skill === '') {
            return 'text-gray-700 ring-1 ring-gray-200/80';
        }

        $style = $this->resolvedIconicSkillStyle();

        return match ($style) {
            'amber' => 'text-amber-900 ring-1 ring-amber-200/70',
            'emerald' => 'text-emerald-900 ring-1 ring-emerald-200/70',
            'sky' => 'text-sky-900 ring-1 ring-sky-200/70',
            'rose' => 'text-rose-900 ring-1 ring-rose-200/70',
            'violet' => 'text-violet-900 ring-1 ring-violet-200/70',
            'brand' => 'text-[#253B5B] ring-1 ring-[#c5ddef]/90',
            default => 'text-amber-900 ring-1 ring-amber-200/70',
        };
    }

    /**
     * @param  string  $badge  تسمية عربية: مستفيد | متدرب | متطوع
     */
    public function membershipBadgeClasses(string $badge): string
    {
        return match ($badge) {
            'مستفيد' => 'bg-slate-100 text-slate-800 ring-1 ring-slate-200',
            'متدرب' => 'bg-[#EAF2FA] text-[#253B5B] ring-1 ring-[#c5ddef]',
            'متطوع' => 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200',
            default => 'bg-gray-100 text-gray-800 ring-1 ring-gray-200',
        };
    }

    public function resolvedIconicSkillStyle(): string
    {
        $s = (string) ($this->iconic_skill_style ?? '');
        if (! in_array($s, self::allowedIconicSkillStyles(), true)) {
            return 'amber';
        }

        return $s;
    }

    /**
     * @return array{0: bool, 1: bool} hasTrainee, hasVolunteer
     */
    private function membershipBadgeFlags(): array
    {
        $raw = $this->membership_badges;
        $explicitKeys = [];
        if (is_array($raw)) {
            foreach ($raw as $v) {
                if ($v === 'trainee' || $v === 'volunteer') {
                    $explicitKeys[] = $v;
                }
            }
        }

        if ($explicitKeys !== []) {
            return [
                in_array('trainee', $explicitKeys, true),
                in_array('volunteer', $explicitKeys, true),
            ];
        }

        $mt = $this->membership_type;
        if (! $mt instanceof MembershipType) {
            $mt = MembershipType::tryFrom((string) $mt) ?? MembershipType::Beneficiary;
        }

        return [
            $mt === MembershipType::Trainee,
            $mt === MembershipType::Volunteer,
        ];
    }

    /**
     * Default: all builder sections visible (keys used by portal + PDF).
     *
     * @return array<string, bool>
     */
    public static function defaultCvSectionVisibility(): array
    {
        return [
            'bio' => true,
            'skills' => true,
            'languages' => true,
            'office_tools' => true,
            'education' => true,
            'experience' => true,
            'external_courses' => true,
            'links' => true,
            'platform' => true,
            'recommendations' => true,
            'legacy_competency' => true,
        ];
    }

    public function cvSectionVisible(string $key): bool
    {
        $raw = $this->cv_sections_visibility;
        if (! is_array($raw)) {
            return true;
        }

        return ($raw[$key] ?? true) === true;
    }

    /**
     * Headline under name: custom job title, else membership-based fallback.
     */
    public function headlineLabel(MembershipType $membership): string
    {
        $custom = trim((string) ($this->job_title ?? ''));

        return $custom !== '' ? $custom : $membership->label();
    }

    /**
     * PDF export: job line only — never "مستفيد". Uses job_title, else trainee/volunteer label, else hidden.
     */
    public function pdfHeadlineForExport(MembershipType $membership, string $cvLocale): ?string
    {
        $custom = trim((string) ($this->job_title ?? ''));
        if ($custom !== '') {
            return $custom;
        }

        return match ($membership) {
            MembershipType::Trainee => $cvLocale === 'en' ? 'Trainee' : 'متدرب',
            MembershipType::Volunteer => $cvLocale === 'en' ? 'Volunteer' : 'متطوع',
            MembershipType::Beneficiary => null,
        };
    }

    public function cvUiLocale(): string
    {
        $l = $this->cv_language ?? 'ar';

        return $l === 'en' ? 'en' : 'ar';
    }

    /**
     * Legacy free-text section (string only). Structured lists use dedicated methods.
     */
    public function cvSection(string $key): ?string
    {
        $raw = $this->cv_sections ?? [];
        if (! is_array($raw)) {
            return null;
        }
        $v = $raw[$key] ?? null;

        return is_string($v) && $v !== '' ? $v : null;
    }

    /**
     * @return list<array{label: string, url: string, type: ?string}>
     */
    public function cvLinksList(): array
    {
        $raw = $this->cv_sections['links'] ?? [];
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $url = isset($row['url']) ? trim((string) $row['url']) : '';
            if ($url === '') {
                continue;
            }
            $label = isset($row['label']) ? trim((string) $row['label']) : '';
            $type = isset($row['type']) ? trim((string) $row['type']) : '';
            $type = $type !== '' ? $type : null;
            if ($type !== null && ! in_array($type, CvFormOptions::LINK_TYPES, true)) {
                $type = 'Other';
            }
            $out[] = [
                'label' => $label !== '' ? $label : $url,
                'url' => $url,
                'type' => $type,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{skill_name: string, level: string, category: ?string}>
     */
    public function cvSkillsStructured(): array
    {
        $raw = $this->cv_sections['skills'] ?? null;
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = isset($row['skill_name']) ? trim((string) $row['skill_name']) : '';
            if ($name === '') {
                continue;
            }
            $level = isset($row['level']) ? (string) $row['level'] : 'متوسط';
            if (! in_array($level, CvFormOptions::SKILL_LEVELS, true)) {
                $level = 'متوسط';
            }
            $cat = isset($row['category']) ? trim((string) $row['category']) : '';
            if ($cat !== '' && ! in_array($cat, CvFormOptions::SKILL_CATEGORIES, true)) {
                $cat = 'أخرى';
            }

            $out[] = [
                'skill_name' => $name,
                'level' => $level,
                'category' => $cat !== '' ? $cat : null,
            ];
        }

        return $out;
    }

    public function cvSkillsLegacyText(): ?string
    {
        return $this->cvLegacyString('skills', 'skills_legacy');
    }

    /**
     * @return list<array{language_name: string, language_code: string, language_custom: ?string, level: string, highlight_english: bool}>
     */
    public function cvLanguagesStructured(): array
    {
        $raw = $this->cv_sections['languages'] ?? null;
        if (! is_array($raw)) {
            return [];
        }
        $locale = $this->cvUiLocale();
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = isset($row['language_code']) ? trim((string) $row['language_code']) : '';
            $custom = isset($row['language_custom']) ? trim((string) $row['language_custom']) : '';
            $legacyName = isset($row['language_name']) ? trim((string) $row['language_name']) : '';

            if ($code === '' && $legacyName !== '') {
                $code = 'custom';
                $custom = $legacyName;
            }

            if ($code === 'custom' && $custom === '') {
                continue;
            }

            if ($code !== 'custom' && $code !== '' && ! in_array($code, CvLanguagePresets::CODES, true)) {
                $code = 'custom';
                $custom = $legacyName !== '' ? $legacyName : $code;
            }

            if ($code === '' || ($code === 'custom' && $custom === '')) {
                continue;
            }

            $display = $code === 'custom'
                ? $custom
                : CvLanguagePresets::label($code, $locale);

            $level = isset($row['level']) ? (string) $row['level'] : 'متوسط';
            if (! in_array($level, CvFormOptions::LANGUAGE_LEVELS, true)) {
                $level = 'متوسط';
            }
            $highlight = ! empty($row['highlight_english']) || ! empty($row['is_english']);
            if (! $highlight && ($code === 'en' || stripos($display, 'english') !== false || str_contains($display, 'الإنجليزية'))) {
                $highlight = true;
            }
            $out[] = [
                'language_name' => $display,
                'language_code' => $code === 'custom' ? 'custom' : $code,
                'language_custom' => $code === 'custom' ? $custom : null,
                'level' => $level,
                'highlight_english' => $highlight,
            ];
        }

        return $out;
    }

    public function cvLanguagesLegacyText(): ?string
    {
        return $this->cvLegacyString('languages', 'languages_legacy');
    }

    /**
     * Raw-ish rows for edit forms (preserves language_code / legacy language_name).
     *
     * @return list<array<string, mixed>>
     */
    public function cvLanguageRowsForForm(): array
    {
        $raw = $this->cv_sections['languages'] ?? null;
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = isset($row['language_code']) ? trim((string) $row['language_code']) : '';
            $custom = isset($row['language_custom']) ? trim((string) $row['language_custom']) : '';
            $legacy = isset($row['language_name']) ? trim((string) $row['language_name']) : '';
            if ($code === '' && $legacy !== '') {
                $code = 'custom';
                $custom = $legacy;
            }
            if ($code === '') {
                continue;
            }
            $out[] = [
                'language_code' => $code,
                'language_custom' => $custom !== '' ? $custom : null,
                'level' => $row['level'] ?? 'متوسط',
                'highlight_english' => ! empty($row['highlight_english']),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{tool_name: string, level: string}>
     */
    public function cvOfficeToolsStructured(): array
    {
        $raw = $this->cv_sections['office_tools'] ?? null;
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = isset($row['tool_name']) ? trim((string) $row['tool_name']) : '';
            if ($name === '') {
                continue;
            }
            $level = isset($row['level']) ? (string) $row['level'] : 'متوسط';
            if (! in_array($level, CvFormOptions::SKILL_LEVELS, true)) {
                $level = 'متوسط';
            }
            $out[] = ['tool_name' => $name, 'level' => $level];
        }

        return $out;
    }

    /**
     * @return list<array{institution: string, degree_or_program: ?string, field: ?string, start_year: ?string, end_year: ?string, is_current: bool}>
     */
    public function cvEducationStructured(): array
    {
        $raw = $this->cv_sections['education'] ?? null;
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $inst = isset($row['institution']) ? trim((string) $row['institution']) : '';
            if ($inst === '') {
                continue;
            }
            $out[] = [
                'institution' => $inst,
                'degree_or_program' => self::nullableString($row['degree_or_program'] ?? null),
                'field' => self::nullableString($row['field'] ?? null),
                'start_year' => self::nullableString($row['start_year'] ?? null),
                'end_year' => self::nullableString($row['end_year'] ?? null),
                'is_current' => ! empty($row['is_current']),
            ];
        }

        return $out;
    }

    public function cvEducationLegacyText(): ?string
    {
        return $this->cvLegacyString('education', 'education_legacy');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cvExperienceStructured(): array
    {
        $raw = $this->cv_sections['experience'] ?? null;
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = isset($row['title']) ? trim((string) $row['title']) : '';
            if ($title === '') {
                continue;
            }
            $type = CvFormOptions::normalizeWorkMode((string) ($row['type'] ?? 'on_site'));
            $emp = CvFormOptions::normalizeEmployment((string) ($row['employment_type'] ?? 'participation'));
            $out[] = [
                'title' => $title,
                'organization' => self::nullableString($row['organization'] ?? null) ?? '',
                'type' => $type,
                'employment_type' => $emp,
                'start_date' => self::nullableString($row['start_date'] ?? null),
                'end_date' => self::nullableString($row['end_date'] ?? null),
                'is_current' => ! empty($row['is_current']),
                'description' => self::nullableString($row['description'] ?? null),
            ];
        }

        return $out;
    }

    public function cvExperienceLegacyText(): ?string
    {
        return $this->cvLegacyString('experience', 'experience_legacy');
    }

    /**
     * @return list<array{title: string, provider: ?string, date: ?string, certificate_url: ?string, description: ?string}>
     */
    public function cvExternalCoursesStructured(): array
    {
        $raw = $this->cv_sections['external_courses'] ?? null;
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = isset($row['title']) ? trim((string) $row['title']) : '';
            if ($title === '') {
                continue;
            }
            $out[] = [
                'title' => $title,
                'provider' => self::nullableString($row['provider'] ?? null),
                'date' => self::nullableString($row['date'] ?? null),
                'certificate_url' => self::nullableString($row['certificate_url'] ?? null),
                'description' => self::nullableString($row['description'] ?? null),
            ];
        }

        return $out;
    }

    public function cvExternalTrainingLegacyText(): ?string
    {
        $raw = $this->cv_sections ?? [];
        if (! is_array($raw)) {
            return null;
        }
        if (isset($raw['external_training']) && is_string($raw['external_training']) && trim($raw['external_training']) !== '') {
            return trim($raw['external_training']);
        }
        if (isset($raw['external_training_legacy']) && is_string($raw['external_training_legacy'])) {
            $t = trim($raw['external_training_legacy']);

            return $t !== '' ? $t : null;
        }

        return null;
    }

    public function avatarUrl(): ?string
    {
        if (! filled($this->avatar)) {
            return null;
        }

        return asset('storage/'.$this->avatar);
    }

    public static function initialsFromName(string $fullName): string
    {
        $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($fullName, 0, min(2, mb_strlen($fullName))));
    }

    /**
     * Competency cards for portal (only keys with non-empty values).
     *
     * @return list<array{key: string, title: string, level: string}>
     */
    public function presentCompetencyCards(): array
    {
        $raw = $this->competency_levels;
        if (! is_array($raw)) {
            return [];
        }

        $definitions = [
            'english' => 'مستوى اللغة الإنجليزية',
            'office' => 'مستوى برامج الأوفيس',
            'courses' => 'مستوى الدورات',
            'continuous_learning' => 'التعلم المستمر',
        ];

        $out = [];
        foreach ($definitions as $key => $title) {
            $value = $raw[$key] ?? null;
            if (filled($value)) {
                $out[] = [
                    'key' => $key,
                    'title' => $title,
                    'level' => (string) $value,
                ];
            }
        }

        return $out;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    private function cvLegacyString(string $primaryKey, string $legacyKey): ?string
    {
        $raw = $this->cv_sections ?? [];
        if (! is_array($raw)) {
            return null;
        }
        if (isset($raw[$primaryKey]) && is_string($raw[$primaryKey]) && trim($raw[$primaryKey]) !== '') {
            return trim($raw[$primaryKey]);
        }
        if (isset($raw[$legacyKey]) && is_string($raw[$legacyKey])) {
            $t = trim($raw[$legacyKey]);

            return $t !== '' ? $t : null;
        }

        return null;
    }

    private static function nullableString(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $t = trim((string) $v);

        return $t === '' ? null : $t;
    }
}
