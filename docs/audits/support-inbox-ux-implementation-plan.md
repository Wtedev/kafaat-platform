# Support Inbox UX — Implementation Plan (local, uncommitted)

## Preserve
- PR #48 Resend staff-reply email (job, dedupe, `beneficiary_email_sent_at`)
- PR #49 floating toast
- Portal hub routes (list/create/show/reply) + guest FAB create
- Ticket numbers, messages, status events, read cursors, Spatie perms

## Product defaults
- One open ticket = `open` | `in_progress` | `waiting_on_user` (openish); PG partial unique index + txn lock
- Chat allowed when not `closed`/`resolved`; closed/resolved read-only
- No reopen in UI or status machine (terminal → no return)
- Staff reply on `open` → auto `in_progress` when allowed
- Guests: create + ticket number toast; no private chat
- Close: in-app notify linked beneficiary; no new unsafe guest-only mail path (reuse in-app only for close)

## Workstreams
1. Migrations: internal notes table; partial unique index on openish `user_id`
2. Service: one-open, close notify, staff auto in_progress, forbid closed replies, notes CRUD + audit
3. Widget API + FAB chat UI (auth) / guest form unchanged
4. Filament `SupportInbox` page (replace CRUD nav)
5. Tests 1–14, pint, build, screenshots
