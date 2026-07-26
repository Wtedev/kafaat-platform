<?php

namespace App\Services\Security;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Re-encrypt APP_KEY-backed ciphertext columns using the current application key.
 *
 * Decrypt uses the configured Encrypter (current APP_KEY + APP_PREVIOUS_KEYS).
 * Encrypt and post-write verification use the current APP_KEY only.
 *
 * Re-runs are functionally safe: AES-CBC IVs are random, so ciphertext bytes may
 * change even when the plaintext and key are unchanged. Plaintext is never logged.
 */
final class AppDataReencryptionService
{
    public const TARGETS = [
        [
            'table' => 'users',
            'column' => 'identity_number_ciphertext',
            'id_column' => 'id',
        ],
        [
            'table' => 'privacy_correction_payloads',
            'column' => 'encrypted_value',
            'id_column' => 'id',
        ],
    ];

    /**
     * @return array{
     *     scanned: int,
     *     eligible: int,
     *     reencrypted: int,
     *     skipped_null: int,
     *     failed: int,
     *     dry_run: bool,
     * }
     */
    public function run(bool $dryRun = false, int $chunkSize = 100): array
    {
        $chunkSize = max(1, $chunkSize);

        $stats = [
            'scanned' => 0,
            'eligible' => 0,
            'reencrypted' => 0,
            'skipped_null' => 0,
            'failed' => 0,
            'dry_run' => $dryRun,
        ];

        foreach (self::TARGETS as $target) {
            $this->processTarget($target, $dryRun, $chunkSize, $stats);
        }

        return $stats;
    }

    /**
     * @param  array{table: string, column: string, id_column: string}  $target
     * @param  array{scanned: int, eligible: int, reencrypted: int, skipped_null: int, failed: int, dry_run: bool}  $stats
     */
    private function processTarget(array $target, bool $dryRun, int $chunkSize, array &$stats): void
    {
        $table = $target['table'];
        $column = $target['column'];
        $idColumn = $target['id_column'];

        DB::table($table)
            ->select([$idColumn, $column])
            ->orderBy($idColumn)
            ->chunkById($chunkSize, function ($rows) use ($table, $column, $idColumn, $dryRun, &$stats): void {
                $batch = [];

                foreach ($rows as $row) {
                    $stats['scanned']++;

                    $ciphertext = $row->{$column};

                    if ($ciphertext === null || $ciphertext === '') {
                        $stats['skipped_null']++;

                        continue;
                    }

                    $stats['eligible']++;
                    $batch[] = [
                        'id' => $row->{$idColumn},
                        'ciphertext' => (string) $ciphertext,
                    ];
                }

                if ($batch === []) {
                    return;
                }

                if ($dryRun) {
                    foreach ($batch as $item) {
                        try {
                            $this->decryptPayload($item['ciphertext']);
                            $stats['reencrypted']++;
                        } catch (Throwable) {
                            $stats['failed']++;
                        }
                    }

                    return;
                }

                $batchReencrypted = 0;
                $batchFailed = 0;

                try {
                    DB::transaction(function () use ($batch, $table, $column, $idColumn, &$batchReencrypted, &$batchFailed): void {
                        foreach ($batch as $item) {
                            try {
                                $plaintext = $this->decryptPayload($item['ciphertext']);
                                $newCiphertext = Crypt::encryptString($plaintext);
                                $this->assertDecryptsWithCurrentKeyAlone($newCiphertext);

                                $updated = DB::table($table)
                                    ->where($idColumn, $item['id'])
                                    ->update([
                                        $column => $newCiphertext,
                                        'updated_at' => now(),
                                    ]);

                                if ($updated !== 1) {
                                    throw new RuntimeException('Re-encryption update did not affect exactly one row.');
                                }

                                $batchReencrypted++;
                            } catch (Throwable) {
                                $batchFailed++;
                            }
                        }
                    });

                    $stats['reencrypted'] += $batchReencrypted;
                    $stats['failed'] += $batchFailed;
                } catch (Throwable) {
                    // Outer failure (connection, deadlock, etc.): entire chunk rolled back.
                    $stats['failed'] += count($batch);
                }
            }, $idColumn);
    }

    private function decryptPayload(string $ciphertext): string
    {
        try {
            return Crypt::decryptString($ciphertext);
        } catch (DecryptException $e) {
            throw new RuntimeException('Ciphertext decrypt failed.', 0, $e);
        }
    }

    private function assertDecryptsWithCurrentKeyAlone(string $ciphertext): void
    {
        $currentOnly = $this->currentKeyOnlyEncrypter();

        try {
            $currentOnly->decryptString($ciphertext);
        } catch (DecryptException $e) {
            throw new RuntimeException('Post-write verification failed for current APP_KEY.', 0, $e);
        }
    }

    private function currentKeyOnlyEncrypter(): Encrypter
    {
        /** @var Encrypter $encrypter */
        $encrypter = Crypt::getFacadeRoot();

        return new Encrypter($encrypter->getKey(), (string) config('app.cipher'));
    }
}
