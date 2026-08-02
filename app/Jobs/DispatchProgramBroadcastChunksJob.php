<?php

namespace App\Jobs;

use App\Enums\ProgramBroadcastRecipientStatus;
use App\Enums\ProgramBroadcastStatus;
use App\Models\ProgramBroadcast;
use App\Models\ProgramBroadcastRecipient;
use App\Services\ProgramBroadcasts\ProgramBroadcastService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Marks a queued broadcast as sending and dispatches per-chunk send jobs.
 */
class DispatchProgramBroadcastChunksJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    public int $timeout = 120;

    public function __construct(
        public readonly int $broadcastId,
    ) {}

    public function handle(ProgramBroadcastService $service): void
    {
        $broadcast = ProgramBroadcast::query()->find($this->broadcastId);

        if ($broadcast === null) {
            return;
        }

        if (! in_array($broadcast->status, [
            ProgramBroadcastStatus::Queued,
            ProgramBroadcastStatus::Sending,
        ], true)) {
            return;
        }

        ProgramBroadcast::query()
            ->whereKey($broadcast->id)
            ->whereIn('status', [
                ProgramBroadcastStatus::Queued->value,
                ProgramBroadcastStatus::Sending->value,
            ])
            ->update([
                'status' => ProgramBroadcastStatus::Sending->value,
                'sending_started_at' => $broadcast->sending_started_at ?? now(),
            ]);

        $pendingIds = ProgramBroadcastRecipient::query()
            ->where('program_broadcast_id', $broadcast->id)
            ->where('status', ProgramBroadcastRecipientStatus::Pending)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($pendingIds === []) {
            $service->refreshAggregateCounts($broadcast->id);

            return;
        }

        foreach (array_chunk($pendingIds, ProgramBroadcastService::CHUNK_SIZE) as $chunk) {
            SendProgramBroadcastChunkJob::dispatch($broadcast->id, array_map('intval', $chunk));
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::warning('Failed dispatching program broadcast chunks.', [
            'broadcast_id' => $this->broadcastId,
            'exception_class' => $exception !== null ? $exception::class : null,
        ]);
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return ['program-broadcast', 'broadcast:'.$this->broadcastId];
    }
}
