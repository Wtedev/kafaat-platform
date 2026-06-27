<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

trait ActsAsOtpVerifiedUser
{
    /**
     * @param  Authenticatable&User  $user
     */
    protected function actingAsOtpVerified(Authenticatable $user): static
    {
        return $this->actingAs($user)->withSession(['otp_verified' => true]);
    }
}
