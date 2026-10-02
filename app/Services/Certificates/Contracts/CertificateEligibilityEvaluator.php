<?php

namespace App\Services\Certificates\Contracts;

use App\Data\Certificates\EligibilityResult;
use Illuminate\Support\Collection;

interface CertificateEligibilityEvaluator
{
    public function supports(object $registration): bool;

    public function evaluate(object $registration): EligibilityResult;

    /**
     * @param  Collection<int, object>  $registrations
     * @return Collection<int|string, EligibilityResult>
     */
    public function evaluateMany(Collection $registrations): Collection;
}
