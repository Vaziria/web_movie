<?php

return [

    /*
    | Web Push (browser notifications). Generate the key pair once with `php artisan app:push-keys`; it writes
    | VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY to .env. Without keys the feature is off (no button, nothing sent).
    | Changing the keys invalidates every existing subscription.
    */
    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),

    // Contact for push services (mailto: or https: URL).
    'subject' => env('VAPID_SUBJECT', 'mailto:noreply@'.(parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost')),

    // Only these push services are accepted as subscription endpoints (so the server never posts to other hosts).
    'allowed_hosts' => ['googleapis.com', 'mozilla.com', 'windows.com', 'apple.com'],

    // A movie that went live longer ago than this is not announced (e.g. after the cron job was down).
    'announce_within_hours' => 24,

    // How long push services keep an undelivered notification (seconds).
    'ttl' => 86400,

    // A subscription that fails this many sends in a row is removed.
    'max_failures' => 3,

    // Subscriptions per batch.
    'batch' => 200,
];
