<?php

namespace Tests\Unit\Support;

use App\Support\Format\LocaleFormat;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocaleFormatTest extends TestCase
{
    #[Test]
    public function test_to_latin_digits_converts_arabic_numerals_and_percent(): void
    {
        $this->assertSame('123 45% 6789', LocaleFormat::toLatinDigits('١٢٣ ٤٥٪ ٦٧٨٩'));
    }

    #[Test]
    public function test_date_uses_latin_digits_with_arabic_month(): void
    {
        $formatted = LocaleFormat::date('2025-06-15', 'd MMMM y');

        $this->assertSame('15', substr($formatted, 0, 2));
        $this->assertStringNotContainsString('١', $formatted);
        $this->assertStringContainsString('2025', $formatted);
    }

    #[Test]
    public function test_diff_for_humans_uses_arabic_relative_phrases(): void
    {
        $formatted = LocaleFormat::diffForHumans(now()->subMinutes(5));

        $this->assertStringStartsWith('منذ', $formatted);
        $this->assertStringContainsString('دقائق', $formatted);
    }
}
