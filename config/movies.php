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
];
