<?php

namespace App\Http\Controllers\Portal;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\Support\SupportTicketService;
use App\Services\Support\SupportUnreadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalSupportController extends Controller
{
    public const SUCCESS_MESSAGE = 'تم إرسال رسالتك بنجاح.';

    public function index(Request $request, SupportUnreadService $unread): View
    {
        $user = $request->user();

        $query = SupportTicket::query()
            ->ownedBy($user)
            ->with(['latestMessage', 'assignee:id,name'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($search = trim((string) $request->query('q', ''))) {
            $like = '%'.$search.'%';
            $driver = SupportTicket::query()->getConnection()->getDriverName();
            $op = $driver === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($like, $op): void {
                $q->where('subject', $op, $like)
                    ->orWhere('ticket_number', $op, $like)
                    ->orWhere('body', $op, $like);
            });
        }

        if ($status = $request->query('status')) {
            if (SupportTicketStatus::tryFrom((string) $status)) {
                $query->where('status', $status);
            }
        }

        if ($category = $request->query('category')) {
            if (SupportTicketCategory::tryFrom((string) $category)) {
                $query->where('category', $category);
            }
        }

        if ($request->boolean('unread')) {
            $query->whereHas('messages', function ($q) use ($user): void {
                $q->visibleToBeneficiary()
                    ->fromSupport()
                    ->whereRaw(
                        'id > COALESCE((SELECT last_read_message_id FROM support_ticket_read_cursors WHERE support_ticket_id = support_ticket_messages.support_ticket_id AND user_id = ?), 0)',
                        [$user->id]
                    );
            });
        }

        $tickets = $query->paginate(15)->withQueryString();

        $tickets->getCollection()->transform(function (SupportTicket $ticket) use ($unread, $user): SupportTicket {
            $ticket->setAttribute('unread_count', $unread->unreadCountForTicket($ticket, $user));

            return $ticket;
        });

        $stats = [
            'open' => SupportTicket::query()->ownedBy($user)->openish()->count(),
            'unread' => $unread->unreadSupportReplyCount($user),
            'total' => SupportTicket::query()->ownedBy($user)->count(),
        ];

        return view('portal.support.index', [
            'tickets' => $tickets,
            'stats' => $stats,
            'filters' => [
                'q' => $search ?? '',
                'status' => (string) $request->query('status', ''),
                'category' => (string) $request->query('category', ''),
                'unread' => $request->boolean('unread'),
            ],
        ]);
    }

    public function create(): View
    {
        return view('portal.support.create', [
            'categories' => SupportTicketCategory::options(),
        ]);
    }

    public function store(Request $request, SupportTicketService $tickets): RedirectResponse
    {
        $validated = $request->validate(
            [
                'subject' => ['required', 'string', 'max:200'],
                'category' => ['required', 'string', 'in:'.implode(',', array_column(SupportTicketCategory::cases(), 'value'))],
                'body' => ['required', 'string', 'min:10', 'max:4000'],
                'idempotency_key' => ['nullable', 'string', 'max:64'],
            ],
            [
                'subject.required' => 'موضوع المحادثة مطلوب.',
                'category.required' => 'التصنيف مطلوب.',
                'body.required' => 'نص الرسالة مطلوب.',
                'body.min' => 'يرجى كتابة وصف أوضح (10 أحرف على الأقل).',
            ],
        );

        $user = $request->user();
        $idempotencyKey = $validated['idempotency_key'] ?? null;

        if (filled($idempotencyKey)) {
            $cacheKey = 'support:create:'.$user->id.':'.$idempotencyKey;
            if (cache()->has($cacheKey)) {
                $existingId = (int) cache()->get($cacheKey);

                return redirect()
                    ->route('portal.support.show', $existingId)
                    ->with('success', self::SUCCESS_MESSAGE);
            }
        }

        $existingOpen = $tickets->findOpenTicketForUser($user);
        if ($existingOpen !== null) {
            return redirect()
                ->route('portal.support.show', $existingOpen)
                ->with('success', 'لديك محادثة مفتوحة بالفعل.');
        }

        $ticket = $tickets->createAndNotify([
            'subject' => $validated['subject'],
            'category' => $validated['category'],
            'body' => $validated['body'],
            'page_url' => url()->previous(),
        ], $user);

        if (filled($idempotencyKey)) {
            cache()->put('support:create:'.$user->id.':'.$idempotencyKey, $ticket->id, now()->addHour());
        }

        return redirect()
            ->route('portal.support.show', $ticket)
            ->with('success', self::SUCCESS_MESSAGE);
    }

    public function show(Request $request, SupportTicket $supportTicket, SupportUnreadService $unread): View
    {
        $this->authorize('view', $supportTicket);

        $user = $request->user();
        abort_unless((int) $supportTicket->user_id === (int) $user->id, 403);

        $messages = $supportTicket->messages()
            ->visibleToBeneficiary()
            ->with('author:id,name')
            ->orderBy('id')
            ->get();

        $unread->markTicketRead($supportTicket, $user, 'beneficiary');

        return view('portal.support.show', [
            'ticket' => $supportTicket->load(['assignee:id,name', 'relatedProgram:id,title']),
            'messages' => $messages,
            'canReply' => $supportTicket->status->allowsBeneficiaryReply(),
        ]);
    }

    public function reply(Request $request, SupportTicket $supportTicket, SupportTicketService $tickets): RedirectResponse
    {
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

        $tickets->addBeneficiaryReply($supportTicket, $request->user(), $validated['body']);

        return redirect()
            ->route('portal.support.show', $supportTicket)
            ->with('success', self::SUCCESS_MESSAGE);
    }

    public function unreadCount(Request $request, SupportUnreadService $unread): JsonResponse
    {
        $count = $unread->unreadSupportReplyCount($request->user());

        return response()->json([
            'count' => $count,
            'display' => $count > 99 ? '+99' : (string) $count,
            'aria_label' => $count > 0
                ? 'الدعم الفني — '.$count.' رد غير مقروء'
                : 'الدعم الفني',
        ]);
    }
}
