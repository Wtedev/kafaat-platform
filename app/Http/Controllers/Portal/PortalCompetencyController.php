<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\NormalizesPortalCvInput;
use App\Models\Profile;
use App\Services\Portal\CompetencyProfilePresenter;
use App\Services\Portal\CvFormOptions;
use App\Services\Portal\CvLanguagePresets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortalCompetencyController extends Controller
{
    use NormalizesPortalCvInput;

    public function show(Request $request): View
    {
        $data = CompetencyProfilePresenter::make($request->user());

        return view('portal.competency', $data);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $section = $request->validate([
            'section' => ['required', 'string', 'in:bio,skills,languages,office_tools,education,experience,external_courses,links,cv_attachment,cv_display,visibility'],
        ])['section'];

        return match ($section) {
            'bio' => $this->updateBio($request, $user),
            'skills' => $this->updateSkills($request, $user),
            'languages' => $this->updateLanguages($request, $user),
            'office_tools' => $this->updateOfficeTools($request, $user),
            'education' => $this->updateEducation($request, $user),
            'experience' => $this->updateExperience($request, $user),
            'external_courses' => $this->updateExternalCourses($request, $user),
            'links' => $this->updateLinks($request, $user),
            'cv_attachment' => $this->updateCvAttachment($request, $user),
            'cv_display' => $this->updateCvDisplay($request, $user),
            'visibility' => $this->updateSectionVisibility($request, $user),
        };
    }

    private function updateCvDisplay(Request $request, $user): RedirectResponse
    {
        $validated = $request->validate([
            'job_title' => ['nullable', 'string', 'max:160'],
            'cv_language' => ['nullable', Rule::in(['ar', 'en'])],
        ]);

        $payload = [];
        if ($request->has('job_title')) {
            $payload['job_title'] = self::trimOrNull($validated['job_title'] ?? null);
        }
        if ($request->has('cv_language')) {
            $payload['cv_language'] = $validated['cv_language'] ?? 'ar';
        }

        if ($payload === []) {
            return back();
        }

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $payload,
        );

        return back()->with('success', 'تم حفظ إعدادات العرض.');
    }

    private function updateSectionVisibility(Request $request, $user): RedirectResponse
    {
        $key = $request->validate([
            'toggle' => ['required', 'string', Rule::in(array_keys(Profile::defaultCvSectionVisibility()))],
        ])['toggle'];

        $vis = $user->profile?->cv_sections_visibility;
        if (! is_array($vis)) {
            $vis = Profile::defaultCvSectionVisibility();
        }
        $vis[$key] = ! (($vis[$key] ?? true) === true);

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            ['cv_sections_visibility' => $vis],
        );

        return back()->with('success', 'تم تحديث ظهور القسم في السيرة والتصدير.');
    }

    private function mergeCv($user): array
    {
        $cv = $user->profile?->cv_sections;

        return is_array($cv) ? $cv : [];
    }

    private function persistCvSections($user, array $cv): void
    {
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            ['cv_sections' => self::cvSectionsHasContent($cv) ? $cv : null],
        );
    }

    private function ensureLinks(array $cv): array
    {
        if (! isset($cv['links']) || ! is_array($cv['links'])) {
            $cv['links'] = [];
        }

        return $cv;
    }

    private function migrateStringToLegacy(array &$cv, string $key, string $legacyKey): void
    {
        if (isset($cv[$key]) && is_string($cv[$key]) && trim($cv[$key]) !== '') {
            $cv[$legacyKey] = trim($cv[$key]);
            unset($cv[$key]);
        }
    }

    private function migrateExternalTrainingKey(array &$cv): void
    {
        if (isset($cv['external_training']) && is_string($cv['external_training']) && trim($cv['external_training']) !== '') {
            $cv['external_training_legacy'] = trim($cv['external_training']);
            unset($cv['external_training']);
        }
    }

    private function updateBio(Request $request, $user): RedirectResponse
    {
        $validated = $request->validate([
            'bio' => ['nullable', 'string', 'max:1000'],
        ]);

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            ['bio' => self::trimOrNull($validated['bio'] ?? null)],
        );

        return back()->with('success', 'تم حفظ النبذة بنجاح.');
    }

    private function updateSkills(Request $request, $user): RedirectResponse
    {
        $validated = $request->validate([
            'skill_items' => ['nullable', 'array', 'max:50'],
            'skill_items.*.skill_name' => ['nullable', 'string', 'max:120'],
            'skill_items.*.level' => ['nullable', Rule::in(CvFormOptions::SKILL_LEVELS)],
            'skill_items.*.category' => ['nullable', Rule::in(CvFormOptions::SKILL_CATEGORIES)],
        ]);

        $items = [];
        foreach ($validated['skill_items'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['skill_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $level = $row['level'] ?? 'متوسط';
            $cat = isset($row['category']) ? trim((string) $row['category']) : '';
            $items[] = [
                'skill_name' => $name,
                'level' => in_array($level, CvFormOptions::SKILL_LEVELS, true) ? $level : 'متوسط',
                'category' => ($cat !== '' && in_array($cat, CvFormOptions::SKILL_CATEGORIES, true)) ? $cat : null,
            ];
        }

        $cv = $this->mergeCv($user);
        $this->migrateStringToLegacy($cv, 'skills', 'skills_legacy');
        $cv['skills'] = $items;
        $cv = $this->ensureLinks($cv);
        $this->persistCvSections($user, $cv);

        return back()->with('success', 'تم حفظ المهارات بنجاح.');
    }

    private function updateLanguages(Request $request, $user): RedirectResponse
    {
        $validated = $request->validate([
            'language_items' => ['nullable', 'array', 'max:30'],
            'language_items.*.language_code' => ['nullable', 'string', 'max:32'],
            'language_items.*.language_custom' => ['nullable', 'string', 'max:120'],
            'language_items.*.language_name' => ['nullable', 'string', 'max:120'],
            'language_items.*.level' => ['nullable', Rule::in(CvFormOptions::LANGUAGE_LEVELS)],
            'language_items.*.highlight_english' => ['nullable', 'boolean'],
        ]);

        $items = [];
        foreach ($validated['language_items'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = trim((string) ($row['language_code'] ?? ''));
            $custom = trim((string) ($row['language_custom'] ?? ''));
            $legacy = trim((string) ($row['language_name'] ?? ''));

            if ($code === '' && $legacy !== '') {
                $code = 'custom';
                $custom = $legacy;
            }

            if ($code === 'custom' && $custom === '') {
                continue;
            }

            if ($code !== '' && $code !== 'custom' && ! in_array($code, CvLanguagePresets::CODES, true)) {
                continue;
            }

            if ($code === '') {
                continue;
            }

            $level = $row['level'] ?? 'متوسط';
            $items[] = [
                'language_code' => $code,
                'language_custom' => $code === 'custom' ? $custom : null,
                'level' => in_array($level, CvFormOptions::LANGUAGE_LEVELS, true) ? $level : 'متوسط',
                'highlight_english' => ! empty($row['highlight_english']),
            ];
        }

        $cv = $this->mergeCv($user);
        $this->migrateStringToLegacy($cv, 'languages', 'languages_legacy');
        $cv['languages'] = $items;
        $cv = $this->ensureLinks($cv);
        $this->persistCvSections($user, $cv);

        return back()->with('success', 'تم حفظ اللغات بنجاح.');
    }

    private function updateOfficeTools(Request $request, $user): RedirectResponse
    {
        $validated = $request->validate([
            'tool_items' => ['nullable', 'array', 'max:40'],
            'tool_items.*.tool_name' => ['nullable', 'string', 'max:120'],
            'tool_items.*.level' => ['nullable', Rule::in(CvFormOptions::SKILL_LEVELS)],
        ]);

        $items = [];
        foreach ($validated['tool_items'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['tool_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $level = $row['level'] ?? 'متوسط';
            $items[] = [
                'tool_name' => $name,
                'level' => in_array($level, CvFormOptions::SKILL_LEVELS, true) ? $level : 'متوسط',
            ];
        }

        $cv = $this->mergeCv($user);
        $cv['office_tools'] = $items;
        $cv = $this->ensureLinks($cv);
        $this->persistCvSections($user, $cv);

        return back()->with('success', 'تم حفظ الأدوات الرقمية بنجاح.');
    }

    private function updateEducation(Request $request, $user): RedirectResponse
    {
        $validated = $request->validate([
            'education_items' => ['nullable', 'array', 'max:25'],
            'education_items.*.institution' => ['nullable', 'string', 'max:200'],
            'education_items.*.degree_or_program' => ['nullable', 'string', 'max:200'],
            'education_items.*.field' => ['nullable', 'string', 'max:200'],
            'education_items.*.start_year' => ['nullable', 'digits:4'],
            'education_items.*.end_year' => ['nullable', 'digits:4'],
            'education_items.*.is_current' => ['nullable', 'boolean'],
        ]);

        $items = [];
        foreach ($validated['education_items'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $inst = trim((string) ($row['institution'] ?? ''));
            if ($inst === '') {
                continue;
            }
            $items[] = [
                'institution' => $inst,
                'degree_or_program' => self::trimOrNull($row['degree_or_program'] ?? null),
                'field' => self::trimOrNull($row['field'] ?? null),
                'start_year' => self::trimOrNull($row['start_year'] ?? null),
                'end_year' => self::trimOrNull($row['end_year'] ?? null),
                'is_current' => ! empty($row['is_current']),
            ];
        }

        $cv = $this->mergeCv($user);
        $this->migrateStringToLegacy($cv, 'education', 'education_legacy');
        $cv['education'] = $items;
        $cv = $this->ensureLinks($cv);
        $this->persistCvSections($user, $cv);

        return back()->with('success', 'تم حفظ التعليم بنجاح.');
    }

    private function updateExperience(Request $request, $user): RedirectResponse
    {
        $validated = $request->validate([
            'experience_items' => ['nullable', 'array', 'max:40'],
            'experience_items.*.title' => ['nullable', 'string', 'max:200'],
            'experience_items.*.organization' => ['nullable', 'string', 'max:200'],
            'experience_items.*.type' => ['nullable', Rule::in(CvFormOptions::WORK_MODE_KEYS)],
            'experience_items.*.employment_type' => ['nullable', Rule::in(CvFormOptions::EMPLOYMENT_KEYS)],
            'experience_items.*.start_date' => ['nullable', 'date'],
            'experience_items.*.end_date' => ['nullable', 'date'],
            'experience_items.*.is_current' => ['nullable', 'boolean'],
            'experience_items.*.description' => ['nullable', 'string', 'max:4000'],
        ]);

        $items = [];
        foreach ($validated['experience_items'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $type = CvFormOptions::normalizeWorkMode((string) ($row['type'] ?? 'on_site'));
            $emp = CvFormOptions::normalizeEmployment((string) ($row['employment_type'] ?? 'participation'));
            $items[] = [
                'title' => $title,
                'organization' => self::trimOrNull($row['organization'] ?? null) ?? '',
                'type' => $type,
                'employment_type' => $emp,
                'start_date' => self::trimOrNull($row['start_date'] ?? null),
                'end_date' => self::trimOrNull($row['end_date'] ?? null),
                'is_current' => ! empty($row['is_current']),
                'description' => self::trimOrNull($row['description'] ?? null),
            ];
        }

        $cv = $this->mergeCv($user);
        $this->migrateStringToLegacy($cv, 'experience', 'experience_legacy');
        $cv['experience'] = $items;
        $cv = $this->ensureLinks($cv);
        $this->persistCvSections($user, $cv);

        return back()->with('success', 'تم حفظ الخبرات بنجاح.');
    }

    private function updateExternalCourses(Request $request, $user): RedirectResponse
    {
        $validated = $request->validate([
            'external_course_items' => ['nullable', 'array', 'max:40'],
            'external_course_items.*.title' => ['nullable', 'string', 'max:200'],
            'external_course_items.*.provider' => ['nullable', 'string', 'max:200'],
            'external_course_items.*.date' => ['nullable', 'string', 'max:40'],
            'external_course_items.*.certificate_url' => ['nullable', 'string', 'max:500'],
            'external_course_items.*.description' => ['nullable', 'string', 'max:2000'],
        ]);

        $items = [];
        foreach ($validated['external_course_items'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $url = self::trimOrNull($row['certificate_url'] ?? null);
            if ($url !== null && ! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
                $url = 'https://'.$url;
            }
            if ($url !== null && ! filter_var($url, FILTER_VALIDATE_URL)) {
                $url = null;
            }
            $items[] = [
                'title' => $title,
                'provider' => self::trimOrNull($row['provider'] ?? null),
                'date' => self::trimOrNull($row['date'] ?? null),
                'certificate_url' => $url,
                'description' => self::trimOrNull($row['description'] ?? null),
            ];
        }

        $cv = $this->mergeCv($user);
        $this->migrateExternalTrainingKey($cv);
        $cv['external_courses'] = $items;
        $cv = $this->ensureLinks($cv);
        $this->persistCvSections($user, $cv);

        return back()->with('success', 'تم حفظ الدورات والشهادات الخارجية بنجاح.');
    }

    private function updateLinks(Request $request, $user): RedirectResponse
    {
        $request->validate([
            'link_items' => ['nullable', 'array', 'max:15'],
            'link_items.*.label' => ['nullable', 'string', 'max:120'],
            'link_items.*.url' => ['nullable', 'string', 'max:500'],
            'link_items.*.type' => ['nullable', Rule::in(CvFormOptions::LINK_TYPES)],
        ]);

        $cv = $this->mergeCv($user);
        $cv['links'] = self::normalizeLinkItemsFromRequest($request);
        $this->persistCvSections($user, $cv);

        return back()->with('success', 'تم حفظ الروابط بنجاح.');
    }

    private function updateCvAttachment(Request $request, $user): RedirectResponse
    {
        $request->validate([
            'cv' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $existing = $user->profile?->cv_path;
        if ($existing && Storage::disk('public')->exists($existing)) {
            Storage::disk('public')->delete($existing);
        }

        $path = $request->file('cv')->store('cv', 'public');

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            ['cv_path' => $path],
        );

        return back()->with('success', 'تم رفع ملف السيرة الذاتية بنجاح.');
    }
}
