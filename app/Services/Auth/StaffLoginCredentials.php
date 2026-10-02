<?php

namespace App\Services\Auth;

use App\Support\Auth\EmailNormalizer;
use SensitiveParameter;

final class StaffLoginCredentials
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromFormData(#[SensitiveParameter] array $data): array
    {
        $email = $data['email'] ?? '';

        return [
            'email' => is_string($email) ? EmailNormalizer::normalize($email) : $email,
            'password' => $data['password'],
        ];
    }
}
