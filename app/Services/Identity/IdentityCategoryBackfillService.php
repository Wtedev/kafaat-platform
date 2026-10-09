<?php

namespace App\Services\Identity;

use App\Enums\IdentityCategory;
use App\Enums\IdentityType;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class IdentityCategoryBackfillService
{
    /**
     * @return array{
     *     scanned: int,
     *     saudi: int,
     *     resident: int,
     *     no_number: int,
     *     type_mismatch: int,
     *     invalid_prefix: int,
     *     decrypt_failed: int,
     *     updated: int,
     * }
     */
    public function run(bool $dryRun = false, int $chunkSize = 100): array
    {
        $stats = [
            'scanned' => 0,
            'saudi' => 0,
            'resident' => 0,
            'no_number' => 0,
            'type_mismatch' => 0,
            'invalid_prefix' => 0,
            'decrypt_failed' => 0,
            'updated' => 0,
        ];

        $columns = ['id', 'identity_type', 'identity_number_ciphertext'];
        if (Schema::hasColumn('users', 'identity_category')) {
            $columns[] = 'identity_category';
        }

        User::query()
            ->select($columns)
            ->orderBy('id')
            ->chunkById(max(1, $chunkSize), function ($users) use (&$stats, $dryRun): void {
                foreach ($users as $user) {
                    $stats['scanned']++;
                    $this->processUser($user, $stats, $dryRun);
                }
            });

        return $stats;
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function processUser(User $user, array &$stats, bool $dryRun): void
    {
        if (! filled($user->identity_number_ciphertext)) {
            $stats['no_number']++;

            if (! $dryRun && ($user->identity_category !== null || $user->identity_type !== null)) {
                // Leave type/category alone when there is no ciphertext; counts only.
            }

            return;
        }

        $normalized = IdentityNumberService::digitsFromCiphertext((string) $user->identity_number_ciphertext);

        if ($normalized === null) {
            $stats['decrypt_failed']++;
            Log::warning('identity:backfill-category decrypt failed', [
                'user_id' => $user->id,
            ]);

            return;
        }

        $category = IdentityNumberService::categoryFromNumber($normalized);

        if ($category === null) {
            $stats['invalid_prefix']++;

            return;
        }

        if ($category === IdentityCategory::Saudi) {
            $stats['saudi']++;
        } else {
            $stats['resident']++;
        }

        $derivedType = $category->toIdentityType();
        $storedType = $user->identity_type instanceof IdentityType
            ? $user->identity_type
            : IdentityType::tryFrom((string) $user->identity_type);

        if ($storedType instanceof IdentityType && $storedType !== $derivedType) {
            $stats['type_mismatch']++;
        }

        $currentCategory = $user->identity_category instanceof IdentityCategory
            ? $user->identity_category
            : IdentityCategory::tryFrom((string) $user->getAttribute('identity_category'));

        $needsUpdate = $currentCategory !== $category
            || $storedType !== $derivedType;

        if (! $needsUpdate) {
            return;
        }

        if (! $dryRun) {
            if (! Schema::hasColumn('users', 'identity_category')) {
                throw new \RuntimeException(
                    'users.identity_category column is missing; run migrations before write mode.'
                );
            }

            $user->forceFill([
                'identity_category' => $category->value,
                'identity_type' => $derivedType->value,
            ])->saveQuietly();
        }

        $stats['updated']++;
    }
}
