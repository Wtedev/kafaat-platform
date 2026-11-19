<?php

return [
    /*
    | The only accounts that see and handle support tickets (inbox, replies,
    | status, notes, alerts). Admins are not included unless listed here.
    | Comma-separated in SUPPORT_AGENT_EMAILS.
    */
    'agents' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('SUPPORT_AGENT_EMAILS', 'lamaalmushyqih@gmail.com')),
    ))),
];
