<?php

namespace App\Services\Certificates;

use App\Data\Certificates\CertificateElement;
use App\Data\Certificates\EligibilityRules;
use App\Enums\CertificateElementType;
use App\Enums\CertificateEligibilityMode;
use App\Enums\CertificateFieldKey;
use App\Enums\CertificateTemplateStatus;
use App\Jobs\RegenerateCertificatePdfJob;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\LearningPath;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Policies\CertificateTemplatePolicy;
use App\Support\Certificates\CertificateFonts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CertificateDesignService
{
    public function __construct(private CertificateRenderer $renderer) {}

    public function ensureDraft(Model $owner): CertificateTemplate
    {
        $existing = $owner->certificateTemplate;
        if ($existing instanceof CertificateTemplate) {
            return $existing;
        }

        return $owner->certificateTemplate()->create([
            'background_disk' => 'local',
            'page_width_mm' => 297,
            'page_height_mm' => 210,
            'elements' => [],
            'eligibility' => $this->defaultRules($owner),
            'auto_issue' => false,
            'status' => CertificateTemplateStatus::Draft,
            'version' => 1,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     * @param  array<string, mixed>  $eligibility
     */
    public function saveDraft(CertificateTemplate $template, array $elements, array $eligibility): CertificateTemplate
    {
        $this->pullAutoIssue($eligibility);
        $template->update([
            'elements' => $this->normalizeElements($template, $elements),
            'eligibility' => $this->normalizeEligibility($eligibility),
            'auto_issue' => false,
            'status' => CertificateTemplateStatus::Draft,
        ]);

        return $template->refresh();
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     * @return list<string>
     */
    public function approvalGaps(CertificateTemplate $template, array $elements): array
    {
        $messages = [];
        $disk = $template->background_disk ?: 'local';
        $path = $template->background_path;

        if (! is_string($path) || $path === '' || ! Storage::disk($disk)->exists($path)) {
            $messages[] = 'ارفع خلفية الشهادة أولاً.';
        }

        $hasName = false;
        foreach ($elements as $element) {
            if (($element['type'] ?? null) === CertificateElementType::Field->value
                && ($element['key'] ?? null) === CertificateFieldKey::RecipientName->value) {
                $hasName = true;
                break;
            }
        }

        if (! $hasName) {
            $messages[] = 'أضف حقل اسم المستفيد.';
        }

        return $messages;
    }

    public function olderCertificateCount(CertificateTemplate $template, int $nextVersion): int
    {
        return Certificate::query()
            ->where('certificate_template_id', $template->id)
            ->where(function ($query) use ($nextVersion): void {
                $query->whereNull('template_version')
                    ->orWhere('template_version', '<', $nextVersion);
            })
            ->count();
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     * @param  array<string, mixed>  $eligibility
     */
    public function approve(CertificateTemplate $template, array $elements, array $eligibility, bool $regenerate): CertificateTemplate
    {
        $normalized = $this->normalizeElements($template, $elements);
        $this->pullAutoIssue($eligibility);
        $rules = $this->normalizeEligibility($eligibility);
        $nextVersion = ((int) $template->version) + 1;

        $template->update([
            'elements' => $normalized,
            'eligibility' => $rules,
            'auto_issue' => false,
            'status' => CertificateTemplateStatus::Ready,
            'version' => $nextVersion,
        ]);

        $template = $template->refresh();

        if ($regenerate) {
            Certificate::query()
                ->where('certificate_template_id', $template->id)
                ->where(function ($query) use ($template): void {
                    $query->whereNull('template_version')
                        ->orWhere('template_version', '<', $template->version);
                })
                ->orderBy('id')
                ->pluck('id')
                ->each(fn (int|string $id) => RegenerateCertificatePdfJob::dispatch((int) $id));
        }

        return $template;
    }

    public function storeBackground(CertificateTemplate $template, UploadedFile $file): CertificateTemplate
    {
        $stored = $this->reencode($file, 'certificate-backgrounds/'.$template->id, 'backgroundUpload');
        [$widthMm, $heightMm] = $this->pageSize($stored['width_px'], $stored['height_px']);
        $this->deleteStored($template->background_disk, $template->background_path);

        $template->update([
            'background_path' => $stored['path'],
            'background_disk' => 'local',
            'page_width_mm' => $widthMm,
            'page_height_mm' => $heightMm,
        ]);

        return $template->refresh();
    }

    /**
     * @return array{path: string, url: string}
     */
    public function storeElementImage(CertificateTemplate $template, UploadedFile $file): array
    {
        $stored = $this->reencode($file, 'certificate-elements/'.$template->id, 'elementImage');

        return [
            'path' => $stored['path'],
            'url' => $this->elementImageUrl($template, basename($stored['path'])),
        ];
    }

    public function copyFrom(CertificateTemplate $target, CertificateTemplate $source): CertificateTemplate
    {
        $elements = [];
        foreach ($source->elements as $element) {
            $row = $element->toArray();
            $row['id'] = (string) Str::uuid();
            if ($element->type === CertificateElementType::Image) {
                $copied = is_string($element->imagePath)
                    ? $this->copyPrivateFile('local', $element->imagePath, 'certificate-elements/'.$target->id)
                    : null;
                if ($copied === null) {
                    continue;
                }
                $row['image_path'] = $copied;
            }
            $elements[] = $row;
        }

        $backgroundPath = null;
        if (is_string($source->background_path) && $source->background_path !== '') {
            $backgroundPath = $this->copyPrivateFile(
                $source->background_disk ?: 'local',
                $source->background_path,
                'certificate-backgrounds/'.$target->id,
            );
        }

        $this->deleteStored($target->background_disk, $target->background_path);

        $target->update([
            'background_path' => $backgroundPath,
            'background_disk' => 'local',
            'page_width_mm' => $source->page_width_mm,
            'page_height_mm' => $source->page_height_mm,
            'elements' => $elements,
            'status' => CertificateTemplateStatus::Draft,
        ]);

        return $target->refresh();
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     */
    public function rememberPreview(CertificateTemplate $template, array $elements, array $fieldValues): string
    {
        $token = (string) Str::uuid();
        Cache::put($this->previewCacheKey($token), [
            'template_id' => $template->id,
            'elements' => $this->normalizeElements($template, $elements),
            'values' => $fieldValues,
        ], now()->addMinutes(10));

        return URL::temporarySignedRoute(
            'certificate-templates.preview',
            now()->addMinutes(10),
            ['template' => $template, 'token' => $token],
        );
    }

    /**
     * @return array{elements: list<CertificateElement>, values: array<string, string>}|null
     */
    public function pullPreview(CertificateTemplate $template, string $token): ?array
    {
        $payload = Cache::get($this->previewCacheKey($token));
        if (! is_array($payload) || (int) ($payload['template_id'] ?? 0) !== (int) $template->id) {
            return null;
        }

        $elements = [];
        foreach ($payload['elements'] ?? [] as $row) {
            if (is_array($row)) {
                $elements[] = CertificateElement::fromArray($row);
            }
        }

        $values = [];
        foreach ($payload['values'] ?? [] as $key => $value) {
            if (is_string($key)) {
                $values[$key] = is_scalar($value) ? (string) $value : '';
            }
        }

        return ['elements' => $elements, 'values' => $values];
    }

    /**
     * @return array<string, string>
     */
    public function fieldValues(Model $owner, ?int $registrationId): array
    {
        if ($registrationId === null) {
            return $this->renderer->sampleValues();
        }

        $registration = $owner->registrations()
            ->whereKey($registrationId)
            ->with('user')
            ->first();

        if (! $registration instanceof Model) {
            throw ValidationException::withMessages([
                'registration' => 'المستفيد غير مسجّل في هذا النشاط.',
            ]);
        }

        $certificate = new Certificate([
            'user_id' => $registration->user_id,
            'certificateable_type' => $owner->getMorphClass(),
            'certificateable_id' => $owner->getKey(),
            'certificate_number' => 'CERT-PREVIEW',
            'verification_code' => 'preview',
            'issued_at' => now(),
        ]);
        $certificate->setRelation('user', $registration->user);
        $certificate->setRelation('certificateable', $owner);

        $values = [];
        foreach (CertificateFieldKey::forOwner($owner::class) as $key) {
            $values[$key->value] = $key->resolve($certificate);
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function designerPayload(CertificateTemplate $template, Model $owner, User $user): array
    {
        $elements = array_map(
            fn (CertificateElement $element): array => $element->toArray(),
            $template->elements,
        );

        $imageUrls = [];
        foreach ($template->elements as $element) {
            if ($element->type === CertificateElementType::Image && is_string($element->imagePath)) {
                $imageUrls[$element->imagePath] = $this->elementImageUrl($template, basename($element->imagePath));
            }
        }

        $policy = app(CertificateTemplatePolicy::class);
        $ownerClass = $owner::class;
        $copyOptions = $ownerClass::query()
            ->whereKeyNot($owner->getKey())
            ->whereHas('certificateTemplate')
            ->orderBy('title')
            ->limit(200)
            ->get()
            ->filter(fn (Model $candidate): bool => $policy->manageForOwner($user, $candidate))
            ->map(fn (Model $candidate): array => [
                'id' => $candidate->getKey(),
                'title' => $candidate->getAttribute('title'),
            ])
            ->values()
            ->all();

        $registrations = $owner->registrations()
            ->with('user')
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn (Model $registration): array => [
                'id' => $registration->getKey(),
                'name' => $registration->user?->certificateName() ?: 'مستفيد',
            ])
            ->all();

        $fields = [];
        $samples = [];
        foreach (CertificateFieldKey::forOwner($ownerClass) as $key) {
            $fields[] = ['key' => $key->value, 'label' => $key->label()];
            $samples[$key->value] = $key->sample();
        }

        $modes = array_map(
            fn (CertificateEligibilityMode $mode): array => [
                'value' => $mode->value,
                'label' => $mode->label(),
            ],
            CertificateEligibilityMode::forOwner($ownerClass),
        );

        return [
            'programName' => (string) $owner->getAttribute('title'),
            'modes' => $modes,
            'autoIssue' => (bool) $template->auto_issue,
            'status' => $template->status->value,
            'statusLabel' => $template->status->label(),
            'version' => (int) $template->version,
            'pageWidthMm' => (float) $template->page_width_mm,
            'pageHeightMm' => (float) $template->page_height_mm,
            'backgroundUrl' => $this->backgroundUrl($template),
            'elements' => $elements,
            'imageUrls' => $imageUrls,
            'eligibility' => $template->eligibility->toArray(),
            'fields' => $fields,
            'samples' => $samples,
            'fonts' => CertificateFonts::families(),
            'registrations' => $registrations,
            'copyOptions' => $copyOptions,
        ];
    }

    public function backgroundUrl(CertificateTemplate $template): ?string
    {
        $path = $template->background_path;
        $disk = $template->background_disk ?: 'local';
        if (! is_string($path) || $path === '' || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        return URL::temporarySignedRoute(
            'certificate-templates.background',
            now()->addMinutes(20),
            ['template' => $template],
        );
    }

    public function elementImageUrl(CertificateTemplate $template, string $filename): string
    {
        return URL::temporarySignedRoute(
            'certificate-templates.element-image',
            now()->addMinutes(20),
            ['template' => $template, 'filename' => $filename],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     * @return list<array<string, mixed>>
     */
    public function normalizeElements(CertificateTemplate $template, array $elements): array
    {
        $normalized = [];
        $prefix = 'certificate-elements/'.$template->id.'/';

        foreach (array_values($elements) as $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages([
                    'elements' => 'عنصر غير صالح.',
                ]);
            }

            $element = CertificateElement::fromArray($row)->toArray();
            if ($element['type'] === CertificateElementType::Image->value) {
                $path = (string) ($element['image_path'] ?? '');
                if (! str_starts_with($path, $prefix) || str_contains($path, '..')) {
                    throw ValidationException::withMessages([
                        'image_path' => 'صورة العنصر غير مرتبطة بهذا القالب.',
                    ]);
                }
            }
            $normalized[] = $element;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $eligibility
     */
    public function normalizeEligibility(array $eligibility): EligibilityRules
    {
        foreach (['min_attendance', 'min_score', 'min_average', 'min_approved_hours'] as $key) {
            if (array_key_exists($key, $eligibility) && $eligibility[$key] === '') {
                $eligibility[$key] = null;
            }
        }

        return EligibilityRules::fromArray($eligibility);
    }

    /**
     * @param  array<string, mixed>  $eligibility
     */
    private function pullAutoIssue(array &$eligibility): bool
    {
        $value = $eligibility['auto_issue'] ?? false;
        unset($eligibility['auto_issue']);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function defaultRules(Model $owner): EligibilityRules
    {
        if ($owner instanceof LearningPath) {
            return EligibilityRules::legacyPathCourses();
        }

        if ($owner instanceof VolunteerOpportunity) {
            return EligibilityRules::legacyVolunteerHours((float) $owner->hours_expected);
        }

        return EligibilityRules::legacyProgramAverage();
    }

    /**
     * @return array{path: string, width_px: int, height_px: int}
     */
    private function reencode(UploadedFile $file, string $directory, string $errorKey): array
    {
        $fail = function (string $message) use ($errorKey): never {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        };

        if ($file->getSize() !== false && $file->getSize() > 10 * 1024 * 1024) {
            $fail('الحد الأقصى للملف 10 ميغابايت.');
        }

        $bytes = file_get_contents($file->getRealPath() ?: '');
        if ($bytes === false || $bytes === '') {
            $fail('تعذر قراءة الملف.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            $fail('يُقبل PNG أو JPG فقط، ونوع الملف لا يطابق صورة.');
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            $fail('الملف ليس صورة صالحة.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width > 10000 || $height > 10000) {
            imagedestroy($image);
            $fail('أبعاد الصورة أكبر من الحد المسموح.');
        }

        $extension = $mime === 'image/png' ? 'png' : 'jpg';
        ob_start();
        if ($extension === 'png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagepng($image, null, 6);
        } else {
            imagejpeg($image, null, 90);
        }
        $encoded = ob_get_clean();
        imagedestroy($image);

        if (! is_string($encoded) || $encoded === '') {
            $fail('تعذر إعادة ترميز الصورة.');
        }

        $path = trim($directory, '/').'/'.Str::uuid().'.'.$extension;
        Storage::disk('local')->put($path, $encoded);

        return ['path' => $path, 'width_px' => $width, 'height_px' => $height];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function pageSize(int $widthPx, int $heightPx): array
    {
        $ratio = $widthPx / max(1, $heightPx);
        $landscape = 297 / 210;
        $portrait = 210 / 297;
        $close = fn (float $value, float $target): bool => abs($value - $target) / $target <= 0.015;

        if ($close($ratio, $landscape)) {
            return [297.0, 210.0];
        }

        if ($close($ratio, $portrait)) {
            return [210.0, 297.0];
        }

        return [297.0, round(297 * $heightPx / $widthPx, 2)];
    }

    private function copyPrivateFile(string $disk, string $path, string $directory): ?string
    {
        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'png');
        if (! in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
            $extension = 'png';
        }
        $target = trim($directory, '/').'/'.Str::uuid().'.'.$extension;
        Storage::disk('local')->put($target, $storage->get($path));

        return $target;
    }

    private function deleteStored(?string $disk, ?string $path): void
    {
        if (! is_string($disk) || ! is_string($path) || $path === '') {
            return;
        }

        Storage::disk($disk)->delete($path);
    }

    private function previewCacheKey(string $token): string
    {
        return 'certificate-preview:'.$token;
    }
}
