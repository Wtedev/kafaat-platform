<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ExperienceHouseRequest;
use App\Services\Documents\PrivateDocumentsStorage;
use App\Services\Surveys\TurnstileVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * نموذج طلبات مشروع بيوت الخبرة — صفحة غير مدرجة في قوائم الموقع، يُوصل إليها برابط مباشر فقط.
 */
class ExperienceHouseRequestController extends Controller
{
    private const SESSION_DONE = 'experience_houses.submitted_reference';

    public function __construct(private readonly TurnstileVerifier $turnstile) {}

    public function cover(): View
    {
        return view('public.experience-houses.cover', [
            'departments' => ExperienceHouseRequest::DEPARTMENTS,
            'icons' => ExperienceHouseRequest::DEPARTMENT_ICONS,
        ]);
    }

    public function create(): View
    {
        return view('public.experience-houses.form', [
            'departments' => ExperienceHouseRequest::DEPARTMENTS,
            'supportTypes' => ExperienceHouseRequest::SUPPORT_TYPES,
            'regions' => ExperienceHouseRequest::REGIONS,
            'staffSizes' => ExperienceHouseRequest::STAFF_SIZES,
            'priorities' => ExperienceHouseRequest::PRIORITIES,
            'deliveryModes' => ExperienceHouseRequest::DELIVERY_MODES,
            'turnstileSiteKey' => $this->turnstile->siteKey(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // مصيدة بوتات: حقل مخفي يجب أن يبقى فارغاً
        if (filled($request->input('website'))) {
            return redirect()->route('public.experience-houses.create');
        }

        $siteKey = $this->turnstile->siteKey();
        if ($siteKey !== null) {
            $token = (string) ($request->input('cf-turnstile-response') ?: $request->input('turnstile_token', ''));
            if (! $this->turnstile->passes($token, $request->ip())) {
                return back()->withInput()->withErrors(['turnstile' => 'تعذّر التحقق من أنك لست برنامجاً آلياً، حاول مرة أخرى.']);
            }
        }

        $request->merge([
            'contact_phone' => $this->normalizeDigits((string) $request->input('contact_phone', '')),
            'license_number' => $this->normalizeDigits((string) $request->input('license_number', '')),
            'founded_year' => $this->normalizeDigits((string) $request->input('founded_year', '')) ?: null,
        ]);

        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'license_number' => ['required', 'string', 'max:50'],
            'region' => ['required', Rule::in(ExperienceHouseRequest::REGIONS)],
            'city' => ['required', 'string', 'max:100'],
            'founded_year' => ['nullable', 'integer', 'min:1950', 'max:'.now()->year],
            'staff_size' => ['nullable', Rule::in(array_keys(ExperienceHouseRequest::STAFF_SIZES))],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_role' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'regex:/^05\d{8}$/'],
            'contact_email' => ['required', 'email', 'max:255'],
            'departments' => ['required', 'array', 'min:1'],
            'departments.*' => [Rule::in(array_keys(ExperienceHouseRequest::DEPARTMENTS))],
            'other_department' => [Rule::requiredIf(fn () => in_array('other', (array) $request->input('departments', []), true)), 'nullable', 'string', 'max:255'],
            'support_types' => ['required', 'array', 'min:1'],
            'support_types.*' => [Rule::in(array_keys(ExperienceHouseRequest::SUPPORT_TYPES))],
            'challenge' => ['required', 'string', 'min:20', 'max:3000'],
            'expected_outcome' => ['required', 'string', 'max:2000'],
            'priority' => ['required', Rule::in(array_keys(ExperienceHouseRequest::PRIORITIES))],
            'delivery_mode' => ['required', Rule::in(array_keys(ExperienceHouseRequest::DELIVERY_MODES))],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg'],
            'consent' => ['accepted'],
        ], [], $this->attributeNames());

        unset($data['attachment'], $data['consent']);

        if (! in_array('other', $data['departments'], true)) {
            $data['other_department'] = null;
        }

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = 'experience-houses/'.now()->format('Y/m').'/'.Str::uuid().'.'.$file->getClientOriginalExtension();
            PrivateDocumentsStorage::disk()->put($path, file_get_contents($file->getRealPath()));
            $data['attachment_path'] = $path;
            $data['attachment_name'] = Str::limit($file->getClientOriginalName(), 200, '');
        }

        $data['status'] = 'new';
        $data['submitted_ip'] = $request->ip();

        $record = ExperienceHouseRequest::create($data);

        return redirect()
            ->route('public.experience-houses.done')
            ->with(self::SESSION_DONE, $record->fresh()->reference_no);
    }

    public function done(): View|RedirectResponse
    {
        $reference = session(self::SESSION_DONE);
        if (! is_string($reference)) {
            return redirect()->route('public.experience-houses.cover');
        }

        return view('public.experience-houses.done', ['reference' => $reference]);
    }

    private function normalizeDigits(string $value): string
    {
        $value = strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $value = preg_replace('/[\s\-]/u', '', $value) ?? '';

        if (str_starts_with($value, '+9665')) {
            $value = '0'.substr($value, 4);
        } elseif (str_starts_with($value, '9665') && strlen($value) === 12) {
            $value = '0'.substr($value, 3);
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        return [
            'organization_name' => 'اسم الجمعية',
            'license_number' => 'رقم الترخيص',
            'region' => 'المنطقة',
            'city' => 'المدينة',
            'founded_year' => 'سنة التأسيس',
            'staff_size' => 'عدد الموظفين',
            'contact_name' => 'اسم ممثل الجمعية',
            'contact_role' => 'المسمى الوظيفي',
            'contact_phone' => 'رقم الجوال',
            'contact_email' => 'البريد الإلكتروني',
            'departments' => 'الإدارات المطلوب دعمها',
            'other_department' => 'الإدارة الأخرى',
            'support_types' => 'نوع الدعم',
            'challenge' => 'وصف الاحتياج',
            'expected_outcome' => 'النتائج المتوقعة',
            'priority' => 'الأولوية',
            'delivery_mode' => 'أسلوب التنفيذ',
            'attachment' => 'المرفق',
            'consent' => 'الإقرار',
        ];
    }
}
