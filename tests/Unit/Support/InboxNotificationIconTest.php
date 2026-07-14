<?php

namespace Tests\Unit\Support;

use App\Enums\InboxNotificationType;
use App\Support\InboxNotificationIcon;
use BladeUI\Icons\Exceptions\SvgNotFound;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InboxNotificationIconTest extends TestCase
{
    #[Test]
    public function test_all_notification_types_resolve_to_existing_heroicons(): void
    {
        foreach (InboxNotificationType::cases() as $type) {
            $name = InboxNotificationIcon::heroiconFor($type);

            $this->assertNotSame('', $name);

            try {
                svg($name);
            } catch (SvgNotFound $exception) {
                $this->fail("Missing heroicon [{$name}] for type [{$type->value}]: {$exception->getMessage()}");
            }
        }
    }

    #[Test]
    public function test_unknown_type_falls_back_to_bell(): void
    {
        $this->assertSame(InboxNotificationIcon::FALLBACK, InboxNotificationIcon::heroiconFor(null));
    }

    #[Test]
    public function test_registration_and_approval_types_use_distinct_icons(): void
    {
        $this->assertSame(
            'heroicon-o-user-plus',
            InboxNotificationIcon::heroiconFor(InboxNotificationType::StaffNewProgramRegistration),
        );

        $this->assertSame(
            'heroicon-o-check-circle',
            InboxNotificationIcon::heroiconFor(InboxNotificationType::RegistrationApproved),
        );
    }
}
