<?php

/*
| Anti-spam rules. Anything flagged is hidden and the author is put "under review"
| (can read, can't post or message) until a platform admin decides at /admin/moderation.
*/

return [

    // Accounts younger than this are "new" and get stricter limits.
    'new_account_hours' => 72,

    // New accounts may include at most this many links per post/comment/message.
    'new_account_max_links' => 0,

    // Anyone: more links than this in one item is treated as spam.
    'max_links' => 5,

    // New conversations a user may start per day (new accounts / everyone else).
    'new_conversations_per_day' => [5, 30],

    // The same message sent to this many different people within 30 minutes = spam blast.
    'duplicate_message_recipients' => 3,

    // Distinct members reporting a user within 7 days before they're put under review.
    'reports_to_restrict' => 3,

    // Case-insensitive phrases that are always spam. Extend via SPAM_KEYWORDS="a,b,c".
    'keywords' => array_merge([
        'whatsapp me', 'telegram me', 'dm me on telegram', 'crypto signals', 'forex signals',
        'investment opportunity', 'guaranteed profit', 'double your money', 'binary options',
        'click the link in my bio', 'onlyfans', 'casino bonus',
    ], array_filter(array_map('trim', explode(',', (string) env('SPAM_KEYWORDS', ''))))),
];
