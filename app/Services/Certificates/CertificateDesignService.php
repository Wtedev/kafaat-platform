<?php

namespace App\Services\Certificates;

use App\Enums\CertificateFieldKey;
use App\Models\CertificateTemplate;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CertificateDesignService
{
    /** @var list<string> */
    public const FONTS = ['shamel', 'ibmplex', 'diodrum'];

    /** @var list<string> */
    public const WEIGHTS = ['regular', 'bold'];

    /** All Diodrum Arabic cuts shipped with the designer and PDF. */
    /** @var list<string> */
    public const DIODRUM_WEIGHTS = [
        'extralight',
        'light',
        'regular',
        'medium',
        'semibold',
        'bold',
        'heavy',
    ];

    /** @var list<string> */
    public const ALIGNS = ['right', 'center', 'left'];

    /**
     * @return list<string>
     */
    public static function weightsFor(string $font): array
    {
        return $font === 'diodrum' ? self::DIODRUM_WEIGHTS : self::WEIGHTS;
    }

    /**
     * @param  array<mixed>  $elements
     */
    public function save(TrainingProgram $program, User $editor, mixed $hours, array $elements): CertificateTemplate
    {
        $template = $this->templateFor($program, $editor);
        $template->fill([
            'hours' => $this->hours($hours),
            'elements' => $this->withoutAutomaticDates($program, $this->validatedElements($elements)),
            'updated_by' => $editor->id,
        ]);
        $template->save();

        return $template->fresh() ?? $template;
    }

    /**
     * A date that matches the program is left empty so a later program change still prints.
     *
     * @param  array<string, array<string, float|int|string>>  $elements
     * @return array<string, array<string, float|int|string>>
     */
    private function withoutAutomaticDates(TrainingProgram $program, array $elements): array
    {
        $automatic = [
            'start_date' => $program->start_date?->toDateString(),
            'end_date' => $program->end_date?->toDateString(),
        ];

        foreach ($automatic as $key => $programDate) {
            if (! isset($elements[$key]['value']) || $programDate === null) {
                continue;
            }
            if ($elements[$key]['value'] === $programDate) {
                unset($elements[$key]['value']);
            }
        }

        return $elements;
    }

    public function storeBackground(TrainingProgram $program, User $editor, UploadedFile $file): CertificateTemplate
    {
        $mime = $this->detectedMime($file);
        if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            throw ValidationException::withMessages([
                'background' => 'الملف يجب أن يكون صورة PNG أو JPG.',
            ]);
        }

        if ($file->getSize() === false || $file->getSize() > 5 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'background' => 'حجم الصورة يجب ألا يتجاوز 5 ميغابايت.',
            ]);
        }

        $extension = $mime === 'image/png' ? 'png' : 'jpg';
        $directory = 'certificate-templates/'.$program->getKey();
        $stored = $file->storeAs($directory, (string) Str::uuid().'.'.$extension, 'public');
        if ($stored === false) {
            throw ValidationException::withMessages([
                'background' => 'تعذر حفظ صورة الخلفية.',
            ]);
        }

        $template = $this->templateFor($program, $editor);
        $previous = $template->background_path;
        $template->fill([
            'background_path' => $stored,
            'updated_by' => $editor->id,
        ]);
        $template->save();

        if (is_string($previous) && $previous !== '' && $previous !== $stored) {
            Storage::disk('public')->delete($previous);
        }

        return $template->fresh() ?? $template;
    }

    /**
     * @param  array<mixed>  $elements
     * @return array<string, array<string, float|int|string>>
     */
    public function validatedElements(array $elements): array
    {
        $clean = [];

        foreach ($elements as $key => $value) {
            if (! is_string($key) || CertificateFieldKey::tryFrom($key) === null) {
                throw ValidationException::withMessages([
                    'elements' => 'حقل غير معروف في التصميم.',
                ]);
            }

            if (! is_array($value)) {
                throw ValidationException::withMessages([
                    'elements.'.$key => 'إعدادات الحقل غير صالحة.',
                ]);
            }

            $this->rejectHtml($value, 'elements.'.$key);
            $field = CertificateFieldKey::from($key);
            $clean[$key] = $field->isQr()
                ? $this->validatedQr($value, $key)
                : $this->validatedText($value, $key);
        }

        return $clean;
    }

    private function templateFor(TrainingProgram $program, User $editor): CertificateTemplate
    {
        $existing = $program->certificateTemplate;
        if ($existing instanceof CertificateTemplate) {
            return $existing;
        }

        return $program->certificateTemplate()->make([
            'elements' => [],
            'updated_by' => $editor->id,
        ]);
    }

    private function hours(mixed $hours): ?string
    {
        if ($hours === null || $hours === '') {
            return null;
        }

        if (! is_numeric($hours)) {
            throw ValidationException::withMessages([
                'hours' => 'عدد الساعات يجب أن يكون رقماً.',
            ]);
        }

        $number = round((float) $hours, 2);
        if ($number < 0 || $number > 9999.99) {
            throw ValidationException::withMessages([
                'hours' => 'عدد الساعات خارج النطاق المسموح.',
            ]);
        }

        return number_format($number, 2, '.', '');
    }

    /**
     * @param  array<mixed>  $value
     */
    private function rejectHtml(array $value, string $key): void
    {
        foreach ($value as $item) {
            if (is_array($item)) {
                $this->rejectHtml($item, $key);

                continue;
            }

            if (is_string($item) && (str_contains($item, '<') || str_contains($item, '>'))) {
                throw ValidationException::withMessages([
                    $key => 'لا يُسمح بوسوم HTML في التصميم.',
                ]);
            }
        }
    }

    /**
     * @param  array<mixed>  $value
     * @return array<string, float|int|string>
     */
    private function validatedText(array $value, string $key): array
    {
        $allowed = ['x', 'y', 'font', 'weight', 'size', 'color', 'align', 'width'];
        if (in_array($key, ['start_date', 'end_date'], true)) {
            $allowed[] = 'value';
        }
        $this->rejectUnknown($value, $allowed, $key);

        $font = $value['font'] ?? null;
        $weight = $value['weight'] ?? null;
        $align = $value['align'] ?? null;
        $color = is_string($value['color'] ?? null) ? $value['color'] : '';

        if (! is_string($font) || ! in_array($font, self::FONTS, true)) {
            throw ValidationException::withMessages([
                'elements.'.$key => 'الخط غير مدعوم.',
            ]);
        }

        if (! is_string($weight) || ! in_array($weight, self::weightsFor($font), true)) {
            throw ValidationException::withMessages([
                'elements.'.$key => 'وزن الخط غير مدعوم.',
            ]);
        }

        if (! is_string($align) || ! in_array($align, self::ALIGNS, true)) {
            throw ValidationException::withMessages([
                'elements.'.$key => 'محاذاة النص غير مدعومة.',
            ]);
        }

        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color) !== 1) {
            throw ValidationException::withMessages([
                'elements.'.$key => 'لون غير صالح.',
            ]);
        }

        $clean = [
            'x' => $this->percent($value['x'] ?? null, $key),
            'y' => $this->percent($value['y'] ?? null, $key),
            'font' => $font,
            'weight' => $weight,
            'size' => $this->size($value['size'] ?? null, $key),
            'color' => strtolower($color),
            'align' => $align,
            'width' => $this->width($value['width'] ?? null, $key),
        ];
        $date = $this->optionalDate($value['value'] ?? null, $key);
        if ($date !== null) {
            $clean['value'] = $date;
        }

        return $clean;
    }

    /**
     * @param  array<mixed>  $value
     * @return array<string, float|int>
     */
    private function validatedQr(array $value, string $key): array
    {
        $this->rejectUnknown($value, ['x', 'y', 'size'], $key);

        return [
            'x' => $this->percent($value['x'] ?? null, $key),
            'y' => $this->percent($value['y'] ?? null, $key),
            'size' => $this->size($value['size'] ?? null, $key),
        ];
    }

    /**
     * @param  array<mixed>  $value
     * @param  list<string>  $allowed
     */
    private function rejectUnknown(array $value, array $allowed, string $key): void
    {
        foreach (array_keys($value) as $property) {
            if (! in_array($property, $allowed, true)) {
                throw ValidationException::withMessages([
                    'elements.'.$key => 'إعداد غير معروف في الحقل.',
                ]);
            }
        }
    }

    private function optionalDate(mixed $value, string $key): ?string
    {
        if (! in_array($key, ['start_date', 'end_date'], true) || $value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw ValidationException::withMessages([
                'elements.'.$key => 'التاريخ يجب أن يكون بصيغة سنة-شهر-يوم.',
            ]);
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
            throw ValidationException::withMessages([
                'elements.'.$key => 'التاريخ غير صالح.',
            ]);
        }

        return $parsed->format('Y-m-d');
    }

    private function percent(mixed $value, string $key): float
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'elements.'.$key => 'موضع العنصر يجب أن يكون بين 0 و 100.',
            ]);
        }

        $number = round((float) $value, 2);
        if ($number < 0 || $number > 100) {
            throw ValidationException::withMessages([
                'elements.'.$key => 'موضع العنصر يجب أن يكون بين 0 و 100.',
            ]);
        }

        return $number;
    }

    private function size(mixed $value, string $key): float
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'elements.'.$key => 'حجم الخط يجب أن يكون بين 8 و 96.',
            ]);
        }

        $number = round((float) $value, 2);
        if ($number < 8 || $number > 96) {
            throw ValidationException::withMessages([
                'elements.'.$key => 'حجم الخط يجب أن يكون بين 8 و 96.',
            ]);
        }

        return $number;
    }

    private function width(mixed $value, string $key): float
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'elements.'.$key => 'عرض العنصر يجب أن يكون بين 1 و 100.',
            ]);
        }

        $number = round((float) $value, 2);
        if ($number < 1 || $number > 100) {
            throw ValidationException::withMessages([
                'elements.'.$key => 'عرض العنصر يجب أن يكون بين 1 و 100.',
            ]);
        }

        return $number;
    }

    private function detectedMime(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return '';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected = $finfo ? finfo_file($finfo, $path) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        return is_string($detected) ? $detected : '';
    }
}
