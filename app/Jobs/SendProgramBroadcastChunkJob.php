<?php

namespace App\Jobs;

use App\Services\ProgramBroadcasts\ProgramBroadcastService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Sends one chunk of program broadcast recipients (one email each).
 * Idempotent: only pending recipients are sent; retries skip already-sent rows.
 */
class SendProgramBroadcastChunkJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [15, 30, 60, 120, 300];

    public int $timeout = 180;

    /**
     * @param  list<int>  $recipientIds
     */
    public function __construct(
        public readonly int $broadcastId,
        public readonly array $recipientIds,
    ) {}

    public function handle(ProgramBroadcastService $service): void
    {
        try {
            $service->processRecipientChunk($this->broadcastId, $this->recipientIds);
        } catch (TransportExceptionInterface $e) {
            Log::warning('Program broadcast chunk hit transport error; will retry.', [
                'broadcast_id' => $this->broadcastId,
                'recipient_count' => count($this->recipientIds),
                'exception_class' => $e::class,
            ]);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Program broadcast chunk job exhausted retries.', [
            'broadcast_id' => $this->broadcastId,
            'recipient_count' => count($this->recipientIds),
            'exception_class' => $exception !== null ? $exception::class : null,
        ]);

        // Ensure aggregates settle even if the last attempt threw.
        try {
            app(ProgramBroadcastService::class)->refreshAggregateCounts($this->broadcastId);
        } catch (Throwable) {
            // ignore
        }
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return ['program-broadcast', 'broadcast:'.$this->broadcastId];
    }
}
