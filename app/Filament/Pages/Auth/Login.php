<?php

namespace App\Filament\Pages\Auth;

use App\Services\Auth\StaffLoginCredentials;
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
        return StaffLoginCredentials::fromFormData($data);
    }
}
