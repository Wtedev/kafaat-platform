<?php

namespace App\Services\Privacy;

use App\Enums\AuditLogResult;
use App\Enums\IdentityType;
use App\Enums\PrivacyCorrectionFieldCode;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacyRequestType;
use App\Models\Certificate;
use App\Models\PrivacyCorrectionPayload;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Identity\IdentityNumberService;
use App\Services\Identity\PersonNameService;
use App\Services\Identity\SaudiPhoneService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PrivacyCorrectionService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function userHasCertificates(User $user): bool
    {
        return Certificate::query()->where('user_id', $user->id)->exists();
    }

    public function storeSensitivePayload(
        PrivacyRequest $privacyRequest,
        PrivacyCorrectionFieldCode $field,
        string $plaintextValue,
        ?IdentityType $identityType = null,
    ): PrivacyCorrectionPayload {
        if ($field !== PrivacyCorrectionFieldCode::IdentityNumber) {
            $encrypted = Crypt::encryptString($plaintextValue);

            return PrivacyCorrectionPayload::query()->updateOrCreate(
                ['privacy_request_id' => $privacyRequest->id],
                [
                    'field_code' => $field->value,
                    'encrypted_value' => $encrypted,
                    'value_lookup_hash' => null,
                    'value_last4' => null,
                    'expires_at' => now()->addDays(30),
                    'consumed_at' => null,
                ],
            );
        }

        $normalized = IdentityNumberService::normalize($plaintextValue);
        if ($normalized === null || $identityType === null) {
            throw new InvalidArgumentException('invalid_identity');
        }

        if (! IdentityNumberService::isValidFormat($normalized)) {
            throw new InvalidArgumentException('invalid_identity');
        }

        if (IdentityNumberService::isDuplicate($normalized, $privacyRequest->user_id)) {
            throw new InvalidArgumentException('duplicate_identity');
        }

        $storagePayload = IdentityNumberService::prepareStoragePayload($plaintextValue, $identityType);

        return PrivacyCorrectionPayload::query()->updateOrCreate(
            ['privacy_request_id' => $privacyRequest->id],
            [
                'field_code' => $field->value,
                'encrypted_value' => $storagePayload['identity_number_ciphertext'],
                'value_lookup_hash' => $storagePayload['identity_number_lookup_hash'],
                'value_last4' => $storagePayload['identity_number_last4'],
                'expires_at' => now()->addDays(30),
                'consumed_at' => null,
            ],
        );
    }

    public function apply(PrivacyRequest $privacyRequest, User $actor): PrivacyRequest
    {
        if ($privacyRequest->request_type !== PrivacyRequestType::DataCorrection) {
            throw new InvalidArgumentException('Not a correction request.');
        }

        if (! in_array($privacyRequest->status, [PrivacyRequestStatus::Approved, PrivacyRequestStatus::PartiallyApproved], true)) {
            throw new InvalidArgumentException('Correction request is not approved.');
        }

        if (! $actor->can('privacy_requests.correction.execute')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('You cannot apply corrections.');
        }

        $field = PrivacyCorrectionFieldCode::tryFrom((string) $privacyRequest->correction_field_code);
        if ($field === null) {
            throw new InvalidArgumentException('Unknown correction field.');
        }

        $user = $privacyRequest->user()->firstOrFail();
        $details = is_array($privacyRequest->request_details) ? $privacyRequest->request_details : [];
        $payload = $privacyRequest->correctionPayload;

        return DB::transaction(function () use ($privacyRequest, $actor, $field, $user, $details, $payload): PrivacyRequest {
            match ($field) {
                PrivacyCorrectionFieldCode::StructuredName => $this->applyStructuredName($user, $details),
                PrivacyCorrectionFieldCode::BirthDate => $this->applyBirthDate($user, (string) ($details['new_value'] ?? '')),
                PrivacyCorrectionFieldCode::Email => $this->applyEmail($user, $payload),
                PrivacyCorrectionFieldCode::IdentityNumber => $this->applyIdentity($user, $payload, $details),
            };

            if ($payload !== null) {
                $payload->forceFill(['consumed_at' => now()])->save();
            }

            $this->auditLogger->recordOrFail(
                $actor,
                'privacy_correction.applied',
                AuditLogResult::Success,
                $user,
                metadata: [
                    'privacy_request_uuid' => $privacyRequest->uuid,
                    'field_code' => $field->value,
                ],
            );

            return $privacyRequest->fresh(['correctionPayload', 'user']);
        });
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function applyStructuredName(User $user, array $details): void
    {
        $parts = PersonNameService::normalizedParts([
            'first_name' => $details['first_name'] ?? '',
            'father_name' => $details['father_name'] ?? '',
            'grandfather_name' => $details['grandfather_name'] ?? '',
            'family_name' => $details['family_name'] ?? '',
        ]);

        $attributes = [...$parts];
        PersonNameService::syncCompatibilityName($attributes, $parts);
        $user->forceFill($attributes)->save();
    }

    private function applyBirthDate(User $user, string $birthDate): void
    {
        if ($birthDate === '') {
            throw new InvalidArgumentException('invalid_birth_date');
        }

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            ['birth_date' => $birthDate],
        );
    }

    private function applyEmail(User $user, ?PrivacyCorrectionPayload $payload): void
    {
        if ($payload === null || $payload->isConsumed()) {
            throw new InvalidArgumentException('missing_payload');
        }

        $email = Crypt::decryptString($payload->encrypted_value);
        $user->forceFill(['email' => $email])->save();
    }

    private function applyIdentity(User $user, ?PrivacyCorrectionPayload $payload, array $details): void
    {
        if ($payload === null || $payload->isConsumed()) {
            throw new InvalidArgumentException('missing_payload');
        }

        $user->forceFill([
            'identity_number_ciphertext' => $payload->encrypted_value,
            'identity_number_lookup_hash' => $payload->value_lookup_hash,
            'identity_number_last4' => $payload->value_last4,
            'identity_confirmed_at' => now(),
        ]);

        $identityType = $details['identity_type'] ?? null;
        if (is_string($identityType) && $identityType !== '') {
            $user->forceFill(['identity_type' => $identityType]);
        }

        try {
            $user->save();
        } catch (QueryException $exception) {
            if (IdentityNumberService::isLookupHashUniqueViolation($exception)) {
                throw new InvalidArgumentException('duplicate_identity');
            }

            throw $exception;
        }
    }
}
