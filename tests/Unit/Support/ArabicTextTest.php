<?php

namespace Tests\Unit\Support;

use App\Support\ArabicText;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArabicTextTest extends TestCase
{
    #[DataProvider('foldProvider')]
    public function test_fold_normalizes_hamza_ta_marbuta_and_alif_maqsurah(string $input, string $expected): void
    {
        $this->assertSame($expected, ArabicText::fold($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function foldProvider(): array
    {
        return [
            'hamza_above' => ['أحمد', 'احمد'],
            'hamza_below' => ['إحسان', 'احسان'],
            'madda' => ['آمنة', 'امنه'],
            'ta_marbuta' => ['فاطمة', 'فاطمه'],
            'alif_maqsurah' => ['مصطفى', 'مصطفي'],
        ];
    }

    public function test_sql_fold_column_wraps_replace_chain(): void
    {
        $sql = ArabicText::sqlFoldColumn('name');

        $this->assertStringContainsString('REPLACE(', $sql);
        $this->assertStringContainsString("'أ'", $sql);
        $this->assertStringContainsString("'ة'", $sql);
        $this->assertStringContainsString("'ى'", $sql);
        $this->assertStringStartsWith('REPLACE(', $sql);
    }
}
