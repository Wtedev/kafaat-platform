<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditLogResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RevealBeneficiaryIdentityRequest;
use App\Models\User;
use App\Services\Access\SensitiveAccessVerification;
use App\Services\Audit\AuditLogger;
use App\Services\Identity\IdentityNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class BeneficiaryIdentityRevealController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function __invoke(RevealBeneficiaryIdentityRequest $request, User $user): JsonResponse
    {
        abort_unless($user->isPortalUser(), 404);

        $actor = $request->user();
        abort_if($actor === null, 403);

        $rateKey = 'identity-reveal:'.$actor->id;

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $this->auditLogger->record(
                $actor,
                'identity.full_view_denied',
                AuditLogResult::Denied,
                $user,
                reasonCode: 'rate_limited',
                metadata: ['reason' => 'rate_limited'],
                request: $request,
            );

            return response()->json([
                'message' => 'تجاوزت عدد المحاولات المسموح. حاول لاحقاً.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($rateKey, 300);

        try {
            SensitiveAccessVerification::assertPasswordConfirmed($actor, (string) $request->input('password'));
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->auditLogger->record(
                $actor,
                'identity.full_view_denied',
                AuditLogResult::Denied,
                $user,
                reasonCode: 'invalid_credentials',
                metadata: ['reason' => 'invalid_credentials'],
                request: $request,
            );

            throw $exception;
        }

        SensitiveAccessVerification::markVerified($request);

        if ($user->identity_number_ciphertext === null) {
            $this->auditLogger->record(
                $actor,
                'identity.full_view_denied',
                AuditLogResult::Failed,
                $user,
                reasonCode: 'not_available',
                metadata: ['reason' => 'not_available'],
                request: $request,
            );

            return response()->json([
                'message' => 'لا يوجد رقم هوية مسجل لهذا المستفيد.',
            ], Response::HTTP_NOT_FOUND);
        }

        $this->auditLogger->recordOrFail(
            $actor,
            'identity.full_viewed',
            AuditLogResult::Success,
            $user,
            reasonCode: 'business_operation',
            metadata: ['reason_code' => 'business_operation'],
            request: $request,
        );

        $fullNumber = IdentityNumberService::decrypt((string) $user->identity_number_ciphertext);

        return response()->json([
            'identity_number' => $fullNumber,
            'expires_in_seconds' => SensitiveAccessVerification::ttlSeconds(),
        ], Response::HTTP_OK, [
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
