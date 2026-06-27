<?php

namespace App\Data\Privacy;

use App\Models\DataDeletionPlan;
use App\Models\DataDeletionPlanStep;
use App\Models\PrivacyRequest;
use App\Models\User;
use Illuminate\Http\Request;

final class DeletionExecutionContext
{
    public function __construct(
        public readonly PrivacyRequest $privacyRequest,
        public readonly DataDeletionPlan $plan,
        public readonly User $target,
        public readonly User $actor,
        public readonly DeletionPlanSnapshot $snapshot,
        public readonly ?Request $request = null,
        public readonly ?DataDeletionPlanStep $step = null,
    ) {}
}
