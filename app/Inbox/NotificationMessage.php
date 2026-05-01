<?php

namespace App\Inbox;

use App\Enums\InboxNotificationType;
use App\Enums\NotificationTargetType;
use Illuminate\Support\Carbon;

/**
 * حمولة تنبيه داخل التطبيق قبل التوزيع على المستلمين.
 */
final readonly class NotificationMessage
{
    public function __construct(
        public InboxNotificationType $type,
        public string $title,
        public string $message,
        public ?int $senderId,
        public NotificationTargetType $targetType,
    ) {}

    /**
     * @param  list<int>  $recipientUserIds
     * @return list<array{user_id: int, title: string, message: string|null, type: string, sender_id: int|null, target_type: string, read_at: null, created_at: Carbon, updated_at: Carbon}>
     */
    public function toRows(iterable $recipientUserIds): array
    {
        $now = now();
        $rows = [];

        foreach ($recipientUserIds as $userId) {
            $rows[] = [
                'user_id' => (int) $userId,
                'title' => $this->title,
                'message' => $this->message,
                'type' => $this->type->value,
                'sender_id' => $this->senderId,
                'target_type' => $this->targetType->value,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }
}
