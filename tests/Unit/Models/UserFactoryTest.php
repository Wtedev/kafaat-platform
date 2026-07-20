<?php

namespace Tests\Unit\Models;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_factory_user_is_active_without_refresh(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->is_active);
        $this->assertSame(AccountStatus::Active, $user->account_status);
    }
}
