<?php

namespace Tests\Feature\PrivacyPhase02;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\Concerns\SeedsActivePrivacyPolicy;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class RegistrationGenderValidationTest extends TestCase
{
    use GeneratesTestIdentityData;
    use RefreshDatabase;
    use SeedsActivePrivacyPolicy;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        $this->seedActivePrivacyPolicy();
    }

    public function test_registration_requires_gender(): void
    {
        $payload = $this->validRegistrationPayload();
        unset($payload['gender']);

        $this->post(route('register'), $payload)
            ->assertSessionHasErrors('gender');
    }
}
