<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PortalProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('profile');

        return view('portal.profile', compact('user'));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female'],
            'competency_english' => ['nullable', 'string', 'max:100'],
            'competency_office' => ['nullable', 'string', 'max:100'],
            'competency_courses' => ['nullable', 'string', 'max:100'],
            'competency_continuous' => ['nullable', 'string', 'max:100'],
            'cv_education' => ['nullable', 'string', 'max:8000'],
            'cv_languages' => ['nullable', 'string', 'max:8000'],
            'cv_skills' => ['nullable', 'string', 'max:8000'],
            'cv_external_training' => ['nullable', 'string', 'max:8000'],
            'cv_experience' => ['nullable', 'string', 'max:8000'],
            'cv_links' => ['nullable', 'array', 'max:10'],
            'cv_links.*.label' => ['nullable', 'string', 'max:120'],
            'cv_links.*.url' => ['nullable', 'string', 'max:500'],
            'cv' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
        ]);

        $competencyLevels = array_filter([
            'english' => $validated['competency_english'] ?? null,
            'office' => $validated['competency_office'] ?? null,
            'courses' => $validated['competency_courses'] ?? null,
            'continuous_learning' => $validated['competency_continuous'] ?? null,
        ], fn ($v) => filled($v));

        $cvLinksNormalized = [];
        foreach ($request->input('cv_links', []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $label = isset($row['label']) ? trim((string) $row['label']) : '';
            $url = isset($row['url']) ? trim((string) $row['url']) : '';
            if ($url === '') {
                continue;
            }
            if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
                $url = 'https://'.$url;
            }
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $cvLinksNormalized[] = [
                'label' => $label !== '' ? $label : (parse_url($url, PHP_URL_HOST) ?: $url),
                'url' => $url,
            ];
            if (count($cvLinksNormalized) >= 10) {
                break;
            }
        }

        $cvBlock = [
            'education' => self::trimOrNull($validated['cv_education'] ?? null),
            'languages' => self::trimOrNull($validated['cv_languages'] ?? null),
            'skills' => self::trimOrNull($validated['cv_skills'] ?? null),
            'external_training' => self::trimOrNull($validated['cv_external_training'] ?? null),
            'experience' => self::trimOrNull($validated['cv_experience'] ?? null),
            'links' => $cvLinksNormalized,
        ];

        $hasCv = collect($cvBlock)->except('links')->filter(fn ($v) => filled($v))->isNotEmpty()
            || count($cvLinksNormalized) > 0;

        $profileData = [
            'city' => $validated['city'],
            'bio' => $validated['bio'],
            'birth_date' => $validated['birth_date'],
            'gender' => $validated['gender'],
            'competency_levels' => count($competencyLevels) ? $competencyLevels : null,
            'cv_sections' => $hasCv ? $cvBlock : null,
        ];

        if ($request->hasFile('cv')) {
            $existing = $user->profile?->cv_path;
            if ($existing && Storage::disk('public')->exists($existing)) {
                Storage::disk('public')->delete($existing);
            }
            $profileData['cv_path'] = $request->file('cv')->store('cv', 'public');
        }

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $profileData,
        );

        return back()->with('success', 'تم حفظ الملف الشخصي بنجاح.');
    }

    private static function trimOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $t = trim($value);

        return $t === '' ? null : $t;
    }
}
