<?php

return [
    /*
     | Sandbox the external player iframe. Sandboxing blocks pop-up ads/redirects from the
     | embed host, but many hosts (e.g. vidara.to) detect it and refuse to play, so it is off by default.
     */
    'embed_sandbox' => env('EMBED_SANDBOX', false),

    /*
     | Rewrite "watch page" URLs to the host's embeddable player URL on save.
     | Each entry: regex pattern => replacement.
     */
    'embed_rewrites' => [
        '~^https://(www\.)?vidara\.to/v/([A-Za-z0-9]+)/?(\?.*)?$~' => 'https://vidara.to/e/$2',
    ],

    /*
     | Time zone for the admin panel's publish/schedule times (form input and display), and for API
     | `published_at` values sent without an offset. Stored in UTC.
     */
    'admin_timezone' => env('ADMIN_TIMEZONE', 'Asia/Jakarta'),
    'admin_timezone_label' => env('ADMIN_TIMEZONE_LABEL', 'WIB'),

    /* "Trending" = most views in the last N days (including today). */
    'trending_days' => 7,

    /* Daily view rows older than this are pruned now and then. */
    'view_days_keep' => 90,
];
