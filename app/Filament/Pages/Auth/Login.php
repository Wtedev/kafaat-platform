<?php

namespace App\Filament\Pages\Auth;

use App\Support\Auth\EmailNormalizer;
use Filament\Auth\Pages\Login as BaseLogin;
use SensitiveParameter;

class Login extends BaseLogin
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $email = $data['email'] ?? '';

        return [
            'email' => is_string($email) ? EmailNormalizer::normalize($email) : $email,
            'password' => $data['password'],
        ];
    }
}
