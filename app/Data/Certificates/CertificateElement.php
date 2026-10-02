<?php

namespace App\Data\Certificates;

use App\Enums\CertificateElementType;
use App\Enums\CertificateFieldKey;
use App\Enums\CertificateFontWeight;
use App\Enums\CertificateTextAlign;
use App\Support\Certificates\CertificateFonts;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class CertificateElement
{
    public function __construct(
        public string $id,
        public CertificateElementType $type,
        public ?CertificateFieldKey $key,
        public ?string $text,
        public ?string $prefix,
        public ?string $suffix,
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public ?string $fontFamily,
        public ?float $fontSizePt,
        public ?CertificateFontWeight $fontWeight,
        public ?string $color,
        public ?CertificateTextAlign $align,
        public bool $autoShrink,
        public ?float $minFontSizePt,
        public ?string $imagePath,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public static function fromArray(array $data): self
    {
        $validated = self::validate($data);
        $type = CertificateElementType::from($validated['type']);
        $textual = in_array($type, [CertificateElementType::Field, CertificateElementType::Text], true);

        return new self(
            id: $validated['id'],
            type: $type,
            key: $type === CertificateElementType::Field ? CertificateFieldKey::from($validated['key']) : null,
            text: $type === CertificateElementType::Text ? $validated['text'] : null,
            prefix: $type === CertificateElementType::Field ? ($validated['prefix'] ?? null) : null,
            suffix: $type === CertificateElementType::Field ? ($validated['suffix'] ?? null) : null,
            x: round((float) $validated['x'], 2),
            y: round((float) $validated['y'], 2),
            width: round((float) $validated['width'], 2),
            height: round((float) $validated['height'], 2),
            fontFamily: $textual ? $validated['font_family'] : ($validated['font_family'] ?? null),
            fontSizePt: isset($validated['font_size_pt']) ? round((float) $validated['font_size_pt'], 2) : null,
            fontWeight: isset($validated['font_weight']) ? CertificateFontWeight::from($validated['font_weight']) : null,
            color: $validated['color'] ?? null,
            align: isset($validated['align']) ? CertificateTextAlign::from($validated['align']) : null,
            autoShrink: (bool) ($validated['auto_shrink'] ?? false),
            minFontSizePt: isset($validated['min_font_size_pt']) ? round((float) $validated['min_font_size_pt'], 2) : null,
            imagePath: $type === CertificateElementType::Image ? $validated['image_path'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(array $data): array
    {
        $type = $data['type'] ?? null;
        $textual = in_array($type, [CertificateElementType::Field->value, CertificateElementType::Text->value], true);

        $validator = Validator::make($data, [
            'id' => ['required', 'uuid'],
            'type' => ['required', Rule::enum(CertificateElementType::class)],
            'key' => [
                Rule::requiredIf($type === CertificateElementType::Field->value),
                Rule::prohibitedIf($type !== CertificateElementType::Field->value),
                Rule::enum(CertificateFieldKey::class),
            ],
            'text' => [
                Rule::requiredIf($type === CertificateElementType::Text->value),
                Rule::prohibitedIf($type !== CertificateElementType::Text->value),
                'nullable',
                'string',
                'max:500',
            ],
            'prefix' => [
                Rule::prohibitedIf($type !== CertificateElementType::Field->value),
                'nullable',
                'string',
                'max:80',
            ],
            'suffix' => [
                Rule::prohibitedIf($type !== CertificateElementType::Field->value),
                'nullable',
                'string',
                'max:80',
            ],
            'x' => ['required', 'numeric', 'between:0,100', self::twoDecimals()],
            'y' => ['required', 'numeric', 'between:0,100', self::twoDecimals()],
            'width' => ['required', 'numeric', 'gt:0', 'lte:100', self::twoDecimals()],
            'height' => ['required', 'numeric', 'gt:0', 'lte:100', self::twoDecimals()],
            'font_family' => [
                Rule::requiredIf($textual),
                'nullable',
                'string',
                Rule::in(CertificateFonts::families()),
            ],
            'font_size_pt' => [Rule::requiredIf($textual), 'nullable', 'numeric', 'gt:0', 'lte:200'],
            'font_weight' => [Rule::requiredIf($textual), 'nullable', Rule::enum(CertificateFontWeight::class)],
            'color' => [Rule::requiredIf($textual), 'nullable', 'regex:/^#(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/'],
            'align' => [Rule::requiredIf($textual), 'nullable', Rule::enum(CertificateTextAlign::class)],
            'auto_shrink' => ['required', 'boolean'],
            'min_font_size_pt' => ['nullable', 'numeric', 'gt:0', 'lte:200'],
            'image_path' => [
                Rule::requiredIf($type === CertificateElementType::Image->value),
                Rule::prohibitedIf($type !== CertificateElementType::Image->value),
                'nullable',
                'string',
                'max:255',
            ],
        ], [
            'key.required' => 'عنصر الحقل يحتاج مفتاحاً من القائمة المغلقة.',
            'key.prohibited' => 'المفتاح مسموح لعنصر الحقل فقط.',
            'key.enum' => 'مفتاح الحقل غير موجود.',
            'color.regex' => 'لون النص يجب أن يكون بصيغة hex.',
            'font_family.in' => 'خط الشهادة غير متوفر.',
        ]);

        $validator->after(function ($validator) use ($data): void {
            $x = isset($data['x']) ? round((float) $data['x'], 2) : null;
            $y = isset($data['y']) ? round((float) $data['y'], 2) : null;
            $width = isset($data['width']) ? round((float) $data['width'], 2) : null;
            $height = isset($data['height']) ? round((float) $data['height'], 2) : null;

            if ($x !== null && $width !== null && round($x + $width, 2) > 100) {
                $validator->errors()->add('width', 'مجموع الموضع الأفقي والعرض يتجاوز عرض الصفحة.');
            }

            if ($y !== null && $height !== null && round($y + $height, 2) > 100) {
                $validator->errors()->add('height', 'مجموع الموضع الرأسي والارتفاع يتجاوز ارتفاع الصفحة.');
            }

            $shrink = filter_var($data['auto_shrink'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($shrink && ! isset($data['min_font_size_pt'])) {
                $validator->errors()->add('min_font_size_pt', 'التصغير التلقائي يتطلب حداً أدنى لحجم الخط.');
            }

            if ($shrink && isset($data['min_font_size_pt'], $data['font_size_pt'])
                && (float) $data['min_font_size_pt'] > (float) $data['font_size_pt']) {
                $validator->errors()->add('min_font_size_pt', 'الحد الأدنى للخط أكبر من حجم الخط.');
            }
        });

        return $validator->validate();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'key' => $this->key?->value,
            'text' => $this->text,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'x' => $this->x,
            'y' => $this->y,
            'width' => $this->width,
            'height' => $this->height,
            'font_family' => $this->fontFamily,
            'font_size_pt' => $this->fontSizePt,
            'font_weight' => $this->fontWeight?->value,
            'color' => $this->color,
            'align' => $this->align?->value,
            'auto_shrink' => $this->autoShrink,
            'min_font_size_pt' => $this->minFontSizePt,
            'image_path' => $this->imagePath,
        ];
    }

    private static function twoDecimals(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_numeric($value)) {
                return;
            }

            if (abs(((float) $value) - round((float) $value, 2)) > 0.00001) {
                $fail('القيمة '.$attribute.' تقبل منزلتين عشريتين كحد أقصى.');
            }
        };
    }
}
