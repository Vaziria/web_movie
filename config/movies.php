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

    /*
     | Visitors who are not logged in may open this many different movies; then the player asks them to log in.
     | 0 = no limit. Period "total" (used up for good, per browser) or "day" (resets every day, admin time zone).
     | Admin → Settings overrides both.
     */
    'guest_free_movies' => (int) env('GUEST_FREE_MOVIES', 2),
    'guest_limit_period' => env('GUEST_LIMIT_PERIOD', 'total'),

    /* Daily active visitor rows (Admin → Statistics) older than this are pruned. */
    'activity_days_keep' => 400,

    /*
     | Premium (ad-free) player: the direct vidara link is fetched by the server and cached this long (never past the
     | link's own expiry, ~8 h). A failed fetch is retried after stream_retry_seconds. Turn the feature on in
     | Admin -> Settings.
     */
    'vidara_stream_api' => env('VIDARA_STREAM_API', 'https://vidara.to/api/stream'),
    'stream_cache_seconds' => (int) env('STREAM_CACHE_SECONDS', 3600),
    'stream_retry_seconds' => 60,
];
