<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RevealBeneficiaryIdentityRequest;
use App\Models\User;
use App\Services\Identity\BeneficiaryIdentityReveal;
use Illuminate\Http\JsonResponse;

class BeneficiaryIdentityRevealController extends Controller
{
    public function __construct(
        private readonly BeneficiaryIdentityReveal $reveal,
    ) {}

    public function __invoke(RevealBeneficiaryIdentityRequest $request, User $user): JsonResponse
    {
        return $this->reveal->respond($request, $user);
    }
}
