<?php

namespace Tests\Unit\Support\Auth;

use App\Support\Auth\CaseInsensitiveEmailDuplicateScanner;
use Tests\TestCase;

class CaseInsensitiveEmailDuplicateScannerTest extends TestCase
{
    public function test_finds_case_insensitive_duplicates_without_merging(): void
    {
        $duplicates = CaseInsensitiveEmailDuplicateScanner::findInRows([
            ['id' => 1, 'email' => 'User@Example.com'],
            ['id' => 2, 'email' => 'other@example.com'],
            ['id' => 3, 'email' => 'USER@EXAMPLE.COM'],
            ['id' => 4, 'email' => '  user@example.com  '],
        ]);

        $this->assertCount(1, $duplicates);
        $this->assertSame('user@example.com', $duplicates[0]['normalized_email']);
        $this->assertSame(3, $duplicates[0]['aggregate']);
        $this->assertSame('1,3,4', $duplicates[0]['ids']);
    }

    public function test_returns_empty_when_no_duplicates(): void
    {
        $duplicates = CaseInsensitiveEmailDuplicateScanner::findInRows([
            ['id' => 1, 'email' => 'a@example.com'],
            ['id' => 2, 'email' => 'b@example.com'],
        ]);

        $this->assertSame([], $duplicates);
    }
}
