<?php

namespace Tests\Unit\Support\Auth;

use App\Support\Auth\EmailNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmailNormalizerTest extends TestCase
{
    #[DataProvider('normalizeProvider')]
    public function test_normalize(?string $input, string $expected): void
    {
        $this->assertSame($expected, EmailNormalizer::normalize($input));
    }

    /**
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function normalizeProvider(): array
    {
        return [
            'null' => [null, ''],
            'already lowercase' => ['user@example.com', 'user@example.com'],
            'uppercase' => ['USER@EXAMPLE.COM', 'user@example.com'],
            'mixed case' => ['User@Example.com', 'user@example.com'],
            'surrounding whitespace' => ['  User@Example.com  ', 'user@example.com'],
            'tabs and spaces' => ["\tUSER@Example.COM\n", 'user@example.com'],
        ];
    }

    public function test_equals_is_case_and_whitespace_insensitive(): void
    {
        $this->assertTrue(EmailNormalizer::equals('  User@Example.com ', 'USER@EXAMPLE.COM'));
        $this->assertFalse(EmailNormalizer::equals('a@example.com', 'b@example.com'));
    }
}
