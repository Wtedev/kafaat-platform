<?php

namespace App\Http\Controllers\Portal;

use App\Enums\SupportMessageSenderType;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\Support\SupportTicketService;
use App\Services\Support\SupportUnreadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * JSON API for the authenticated «لدي مشكلة» chat widget.
 */
class PortalSupportWidgetController extends Controller
{
    public function state(Request $request, SupportTicketService $tickets, SupportUnreadService $unread): JsonResponse
    {
        $user = $request->user();
        $open = $tickets->findOpenTicketForUser($user);

        if ($open === null) {
            $latestClosed = SupportTicket::query()
                ->ownedBy($user)
                ->whereIn('status', [
                    SupportTicketStatus::Closed->value,
                    SupportTicketStatus::Resolved->value,
                ])
                ->orderByDesc('closed_at')
                ->orderByDesc('id')
                ->first();

            return response()->json([
                'mode' => 'create',
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'closed_ticket' => $latestClosed ? $this->ticketPayload($latestClosed, [], false) : null,
                'history_url' => route('portal.support.index'),
            ]);
        }

        $messages = $this->visibleMessages($open);
        $unread->markTicketRead($open, $user, 'beneficiary');

        return response()->json([
            'mode' => 'conversation',
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'ticket' => $this->ticketPayload($open, $messages, $open->allowsChat()),
            'history_url' => route('portal.support.index'),
        ]);
    }

    public function show(
        Request $request,
        SupportTicket $supportTicket,
        SupportUnreadService $unread,
    ): JsonResponse {
        $this->authorize('view', $supportTicket);
        abort_unless((int) $supportTicket->user_id === (int) $request->user()->id, 403);

        $messages = $this->visibleMessages($supportTicket, (int) $request->query('after', 0));
        $unread->markTicketRead($supportTicket, $request->user(), 'beneficiary');

        return response()->json([
            'ticket' => $this->ticketPayload($supportTicket, $messages, $supportTicket->allowsChat()),
        ]);
    }

    public function store(Request $request, SupportTicketService $tickets): JsonResponse
    {
        $validated = $request->validate(
            [
                'subject' => ['required', 'string', 'max:200'],
                'body' => ['required', 'string', 'min:10', 'max:4000'],
                'page_url' => ['nullable', 'string', 'max:500'],
            ],
            [
                'subject.required' => 'موضوع المشكلة مطلوب.',
                'body.required' => 'وصف المشكلة مطلوب.',
                'body.min' => 'يرجى كتابة وصف أوضح للمشكلة (10 أحرف على الأقل).',
            ],
        );

        $user = $request->user();
        $existing = $tickets->findOpenTicketForUser($user);
        if ($existing !== null) {
            $messages = $this->visibleMessages($existing);

            return response()->json([
                'mode' => 'conversation',
                'created' => false,
                'ticket' => $this->ticketPayload($existing, $messages, $existing->allowsChat()),
                'message' => 'لديك محادثة مفتوحة بالفعل.',
            ]);
        }

        $ticket = $tickets->createAndNotify([
            'subject' => $validated['subject'],
            'category' => SupportTicketCategory::General->value,
            'body' => $validated['body'],
            'page_url' => $validated['page_url'] ?? url()->previous(),
        ], $user);

        $messages = $this->visibleMessages($ticket);

        return response()->json([
            'mode' => 'conversation',
            'created' => $ticket->wasRecentlyCreated,
            'ticket' => $this->ticketPayload($ticket, $messages, $ticket->allowsChat()),
            'message' => 'تم فتح المحادثة.',
        ], $ticket->wasRecentlyCreated ? 201 : 200);
    }

    public function reply(
        Request $request,
        SupportTicket $supportTicket,
        SupportTicketService $tickets,
        SupportUnreadService $unread,
    ): JsonResponse {
        $this->authorize('reply', $supportTicket);
        abort_unless((int) $supportTicket->user_id === (int) $request->user()->id, 403);

        $validated = $request->validate(
            [
                'body' => ['required', 'string', 'min:1', 'max:4000'],
            ],
            [
                'body.required' => 'نص الرد مطلوب.',
            ],
        );

        try {
            $message = $tickets->addBeneficiaryReply($supportTicket, $request->user(), $validated['body']);
        } catch (ValidationException $e) {
            throw $e;
        }

        $unread->markTicketRead($supportTicket->fresh() ?? $supportTicket, $request->user(), 'beneficiary');

        return response()->json([
            'message' => $this->messagePayload($message),
            'ticket' => $this->ticketPayload($supportTicket->fresh() ?? $supportTicket, [], $supportTicket->fresh()?->allowsChat() ?? false),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function visibleMessages(SupportTicket $ticket, int $afterId = 0): array
    {
        $query = $ticket->messages()
            ->visibleToBeneficiary()
            ->with('author:id,name')
            ->orderBy('id');

        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        }

        return $query->get()->map(fn ($m) => $this->messagePayload($m))->all();
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return array<string, mixed>
     */
    private function ticketPayload(SupportTicket $ticket, array $messages, bool $canReply): array
    {
        $status = SupportTicketStatus::coerce($ticket->status);

        return [
            'id' => $ticket->id,
            'number' => $ticket->displayNumber(),
            'subject' => $ticket->subject,
            'status' => $status->value,
            'status_label' => $status->label(),
            'can_reply' => $canReply,
            'is_closed' => $status->isTerminal(),
            'closed_message' => $status->isTerminal()
                ? 'تم إغلاق هذه التذكرة. إذا كنت بحاجة إلى مساعدة إضافية، يمكنك فتح تذكرة جديدة.'
                : null,
            'messages' => $messages,
            'poll_url' => route('portal.support.widget.show', $ticket),
            'reply_url' => route('portal.support.widget.reply', $ticket),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload($message): array
    {
        $sender = $message->sender_type instanceof SupportMessageSenderType
            ? $message->sender_type
            : SupportMessageSenderType::tryFrom((string) $message->sender_type);

        $isBeneficiary = $sender === SupportMessageSenderType::Beneficiary;
        $isSystem = (bool) $message->is_system || $sender === SupportMessageSenderType::System;

        return [
            'id' => $message->id,
            'body' => $message->body,
            'sender' => $sender?->value ?? 'system',
            'side' => $isSystem ? 'system' : ($isBeneficiary ? 'self' : 'support'),
            'label' => $isSystem ? 'النظام' : ($isBeneficiary ? 'أنت' : 'فريق الدعم'),
            'time' => optional($message->created_at)?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'is_system' => $isSystem,
        ];
    }
}
