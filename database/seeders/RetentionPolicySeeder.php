<?php

namespace Database\Seeders;

use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionPolicyStatus;
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
                'trigger_type' => RetentionTriggerEvent::AccountDeletedAt,
                'retention_period_days' => null,
                'action' => RetentionPolicyAction::RetainRestricted,
                'status' => RetentionPolicyStatus::Active,
                'reason' => 'Certificate verification requires retained issuance evidence.',
            ],
            [
                'resource_type' => 'program_registrations',
                'name' => 'Program registration retention',
                'trigger_type' => RetentionTriggerEvent::AccountDeletedAt,
                'retention_period_days' => null,
                'action' => RetentionPolicyAction::RetainRestricted,
                'status' => RetentionPolicyStatus::Active,
                'reason' => 'Registration evidence retained under restricted access pending administrative decision.',
            ],
            [
                'resource_type' => 'path_registrations',
                'name' => 'Path registration retention',
                'trigger_type' => RetentionTriggerEvent::AccountDeletedAt,
                'retention_period_days' => null,
                'action' => RetentionPolicyAction::RetainRestricted,
                'status' => RetentionPolicyStatus::Active,
                'reason' => 'Registration evidence retained under restricted access pending administrative decision.',
            ],
            [
                'resource_type' => 'volunteer_registrations',
                'name' => 'Volunteer registration retention',
                'trigger_type' => RetentionTriggerEvent::AccountDeletedAt,
                'retention_period_days' => null,
                'action' => RetentionPolicyAction::RetainRestricted,
                'status' => RetentionPolicyStatus::Active,
                'reason' => 'Registration evidence retained under restricted access pending administrative decision.',
            ],
            [
                'resource_type' => 'attendance',
                'name' => 'Attendance retention',
                'trigger_type' => RetentionTriggerEvent::AccountDeletedAt,
                'retention_period_days' => null,
                'action' => RetentionPolicyAction::RetainRestricted,
                'status' => RetentionPolicyStatus::Active,
                'reason' => 'Attendance evidence retained under restricted access pending administrative decision.',
            ],
            [
                'resource_type' => 'email_verification_codes',
                'name' => 'OTP cleanup',
                'trigger_type' => RetentionTriggerEvent::ExpiredAt,
                'retention_period_days' => 7,
                'action' => RetentionPolicyAction::Delete,
                'status' => RetentionPolicyStatus::Draft,
                'reason' => 'Operational cleanup of expired OTP records.',
            ],
            [
                'resource_type' => 'user_activity_logs',
                'name' => 'Beneficiary activity cleanup on account deletion',
                'trigger_type' => RetentionTriggerEvent::AccountDeletedAt,
                'retention_period_days' => 0,
                'action' => RetentionPolicyAction::Delete,
                'status' => RetentionPolicyStatus::Active,
                'reason' => 'Portal activity history removed during approved account anonymization.',
            ],
            [
                'resource_type' => 'privacy_export_files',
                'name' => 'Expired privacy export purge',
                'trigger_type' => RetentionTriggerEvent::ExpiredAt,
                'retention_period_days' => 0,
                'action' => RetentionPolicyAction::Delete,
                'status' => RetentionPolicyStatus::Active,
                'reason' => 'Operational purge of expired personal data export archives already approved for production scheduling.',
            ],
            [
                'resource_type' => 'password_reset_tokens',
                'name' => 'Expired password reset cleanup',
                'trigger_type' => RetentionTriggerEvent::CreatedAt,
                'retention_period_days' => 0,
                'action' => RetentionPolicyAction::Delete,
                'status' => RetentionPolicyStatus::Draft,
                'reason' => 'Remove expired password reset tokens after Laravel auth expiry window.',
            ],
            [
                'resource_type' => 'sessions',
                'name' => 'Expired session cleanup',
                'trigger_type' => RetentionTriggerEvent::LastActivityAt,
                'retention_period_days' => 0,
                'action' => RetentionPolicyAction::Delete,
                'status' => RetentionPolicyStatus::Draft,
                'reason' => 'Remove sessions past configured session lifetime.',
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
