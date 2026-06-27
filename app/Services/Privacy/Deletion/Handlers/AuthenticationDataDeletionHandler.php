<?php

namespace App\Services\Privacy\Deletion\Handlers;

use App\Data\Privacy\DeletionExecutionContext;
use App\Enums\DeletionHandlerName;
use App\Models\EmailVerificationCode;
use App\Services\Privacy\AccountDeactivationService;
use App\Services\Privacy\Deletion\Contracts\DeletionHandlerInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AuthenticationDataDeletionHandler implements DeletionHandlerInterface
{
    public function __construct(
        private readonly AccountDeactivationService $accountDeactivationService,
    ) {}

    public function name(): string
    {
        return DeletionHandlerName::AuthenticationData->value;
    }

    public function handle(DeletionExecutionContext $context): void
    {
        $user = $context->target;

        $this->accountDeactivationService->invalidateSessions($user);

        EmailVerificationCode::query()->where('user_id', $user->id)->delete();

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->delete();

        $user->forceFill([
            'remember_token' => null,
        ])->saveQuietly();
    }
}
