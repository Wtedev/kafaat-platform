<?php

namespace App\Services\CandidatePool;

use App\Enums\CandidatePoolPreferenceStatus;
use App\Enums\PrivacyPolicyVersionStatus;
use App\Enums\UserDocumentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CandidatePoolQuery
{
    public function eligibleQuery(): Builder
    {
        $activeVersion = CandidatePoolConsentVersionService::activeVersion();

        return User::query()
            ->where('is_active', true)
            ->whereIn('role_type', ['beneficiary', 'trainee', 'volunteer'])
            ->whereHas('candidatePoolPreference', function (Builder $query) use ($activeVersion): void {
                $query->where('current_status', CandidatePoolPreferenceStatus::Granted);

                if ($activeVersion !== null && $activeVersion->requires_reconsent) {
                    $query->where('current_consent_version_id', $activeVersion->id);
                }
            })
            ->whereHas('profile', function (Builder $query): void {
                $query->whereNotNull('current_cv_document_id')
                    ->whereHas('currentCvDocument', fn (Builder $doc) => $doc->where('status', UserDocumentStatus::Active));
            })
            ->whereNotNull('first_name')
            ->whereNotNull('father_name')
            ->whereNotNull('grandfather_name')
            ->whereNotNull('family_name')
            ->whereNotNull('phone')
            ->whereHas('profile', fn (Builder $q) => $q->whereNotNull('birth_date'));
    }
}
