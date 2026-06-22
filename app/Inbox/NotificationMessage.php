<?php

namespace App\Inbox;

use App\Enums\InboxNotificationType;
use App\Enums\NotificationTargetType;

/**
 * حمولة تنبيه داخل التطبيق قبل التوزيع على المستلمين.
 */
final readonly class NotificationMessage
{
    /**
     * @param  array<string, mixed>|null  $context  روابط الإجراءات في لوحة الإدارة (مفتاح resource + id).
     * @param  bool  $emailable  هل تُرسل نسخة بريدية عامة؟ (false للأنواع التي لها بريد مخصّص مسبقاً لتفادي الازدواج).
     */
    public function __construct(
        public InboxNotificationType $type,
        public string $title,
        public string $message,
        public ?int $senderId,
        public NotificationTargetType $targetType,
        public ?array $context = null,
        public bool $emailable = true,
    ) {}

    /**
     * @param  list<int>  $recipientUserIds
     * @return list<array<string, mixed>>
     */
    public function toRows(iterable $recipientUserIds): array
    {
        $now = now();
        $rows = [];
        $contextJson = $this->context === null ? null : json_encode($this->context, JSON_UNESCAPED_UNICODE);

        foreach ($recipientUserIds as $userId) {
            $rows[] = [
                'user_id' => (int) $userId,
                'title' => $this->title,
                'message' => $this->message,
                'type' => $this->type->value,
                'sender_id' => $this->senderId,
                'target_type' => $this->targetType->value,
                'context' => $contextJson,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }
}
