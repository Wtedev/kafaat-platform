<?php

namespace Database\Seeders;

use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionTriggerEvent;
use App\Models\RetentionPolicy;
use Illuminate\Database\Seeder;

class RetentionPolicySeeder extends Seeder
{
    public function run(): void
    {
        $policies = [
            [
                'resource_type' => 'certificates',
                'name' => 'Certificate verification retention',
                'description' => 'Retain certificate records for public verification until an administrative disposal period is approved.',
                'trigger_event' => RetentionTriggerEvent::AccountDeletedAt,
                'retention_period_days' => null,
                'action' => RetentionPolicyAction::RetainRestricted,
                'enabled' => true,
                'reason' => 'Certificate verification requires retained issuance evidence.',
            ],
            [
                'resource_type' => 'program_registrations',
                'name' => 'Program registration retention',
                'trigger_event' => RetentionTriggerEvent::AccountDeletedAt,
                'retention_period_days' => null,
                'action' => RetentionPolicyAction::RetainRestricted,
                'enabled' => true,
                'reason' => 'Registration evidence retained under restricted access pending administrative decision.',
            ],
            [
                'resource_type' => 'path_registrations',
                'name' => 'Path registration retention',
                'trigger_event' => RetentionTriggerEvent::AccountDeletedAt,
                'retention_period_days' => null,
                'action' => RetentionPolicyAction::RetainRestricted,
                'enabled' => true,
                'reason' => 'Registration evidence retained under restricted access pending administrative decision.',
            ],
            [
                'resource_type' => 'volunteer_registrations',
                'name' => 'Volunteer registration retention',
                'trigger_event' => RetentionTriggerEvent::AccountDeletedAt,
                'retention_period_days' => null,
                'action' => RetentionPolicyAction::RetainRestricted,
                'enabled' => true,
                'reason' => 'Registration evidence retained under restricted access pending administrative decision.',
            ],
            [
                'resource_type' => 'attendance',
                'name' => 'Attendance retention',
                'trigger_event' => RetentionTriggerEvent::AccountDeletedAt,
                'retention_period_days' => null,
                'action' => RetentionPolicyAction::RetainRestricted,
                'enabled' => true,
                'reason' => 'Attendance evidence retained under restricted access pending administrative decision.',
            ],
            [
                'resource_type' => 'email_verification_codes',
                'name' => 'OTP cleanup',
                'trigger_event' => RetentionTriggerEvent::ExpiredAt,
                'retention_period_days' => 7,
                'action' => RetentionPolicyAction::Delete,
                'enabled' => true,
                'reason' => 'Operational cleanup of expired OTP records.',
            ],
            [
                'resource_type' => 'user_activity_logs',
                'name' => 'Beneficiary activity cleanup on account deletion',
                'trigger_event' => RetentionTriggerEvent::AccountDeletedAt,
                'retention_period_days' => 0,
                'action' => RetentionPolicyAction::Delete,
                'enabled' => true,
                'reason' => 'Portal activity history removed during approved account anonymization.',
            ],
        ];

        foreach ($policies as $policy) {
            RetentionPolicy::query()->updateOrCreate(
                [
                    'resource_type' => $policy['resource_type'],
                    'name' => $policy['name'],
                ],
                [
                    ...$policy,
                    'grace_period_days' => 0,
                    'effective_at' => now(),
                ],
            );
        }
    }
}
