<?php

namespace Tests\Feature\Security;

use App\Services\Security\SensitiveDataRedactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SensitiveDataRedactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_redacts_nested_sensitive_keys(): void
    {
        $redacted = SensitiveDataRedactor::redact([
            'password' => 'secret',
            'recipient_email' => 'user@example.com',
            'nested' => ['otp' => '123456', 'token' => 'abc', 'safe' => 'ok'],
        ]);

        $this->assertArrayNotHasKey('password', $redacted);
        $this->assertArrayNotHasKey('recipient_email', $redacted);
        $this->assertSame(['safe' => 'ok'], $redacted['nested']);
    }

    public function test_arabic_labeled_keys_with_sensitive_substrings_are_redacted(): void
    {
        $redacted = SensitiveDataRedactor::redact([
            'user_otp_code' => '123456',
            'notes' => 'safe text',
        ]);

        $this->assertArrayNotHasKey('user_otp_code', $redacted);
        $this->assertSame('safe text', $redacted['notes']);
    }
}
