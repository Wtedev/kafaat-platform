<?php

namespace Tests\Unit\Certificates;

use App\Data\Certificates\CertificateElement;
use App\Enums\CertificateElementType;
use App\Enums\CertificateFontWeight;
use App\Enums\CertificateTextAlign;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CertificateElementTest extends TestCase
{
    public function test_rejects_unknown_field_key(): void
    {
        $this->expectException(ValidationException::class);

        CertificateElement::fromArray($this->element(['key' => 'trainer_name']));
    }

    public function test_rejects_box_that_overflows_the_page_width(): void
    {
        try {
            CertificateElement::fromArray($this->element([
                'x' => 80,
                'width' => 30,
            ]));
            $this->fail('كان يجب رفض الصندوق الذي يتجاوز عرض الصفحة.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('width', $exception->errors());
        }
    }

    public function test_rejects_invalid_color(): void
    {
        try {
            CertificateElement::fromArray($this->element(['color' => 'blue']));
            $this->fail('كان يجب رفض اللون غير الصالح.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('color', $exception->errors());
        }
    }

    public function test_accepts_a_field_element_inside_the_page(): void
    {
        $element = CertificateElement::fromArray($this->element([
            'prefix' => 'إلى ',
            'suffix' => '،',
        ]));

        $this->assertSame(CertificateElementType::Field, $element->type);
        $this->assertSame('إلى ', $element->prefix);
        $this->assertSame(20.5, $element->x);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function element(array $overrides = []): array
    {
        return array_merge([
            'id' => (string) Str::uuid(),
            'type' => CertificateElementType::Field->value,
            'key' => 'recipient_name',
            'x' => 20.5,
            'y' => 40,
            'width' => 60,
            'height' => 12,
            'font_family' => 'ibmplexsansarabic',
            'font_size_pt' => 24,
            'font_weight' => CertificateFontWeight::Bold->value,
            'color' => '#1E3A5F',
            'align' => CertificateTextAlign::Center->value,
            'auto_shrink' => true,
            'min_font_size_pt' => 12,
        ], $overrides);
    }
}
