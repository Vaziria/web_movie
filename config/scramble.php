<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;

return [
    /*
     * Which routes to document. String or array form; use Scramble::routes() for custom selection.
     *
     * 'api_path' => [
     *     'include' => 'api',
     *     'exclude' => ['api/internal'],
     * ],
     *
     * Without *, patterns match path segments (api matches api and api/users, not apiary).
     * With *, Str::is is used (e.g. api/v*).
     *
     * One static include → default server is /{include} and paths are stripped (/users).
     * Multiple includes or wildcards → server defaults to / and paths stay full (/api/users).
     * Override with `servers`, or use Scramble::registerApi() for separate bases.
     */
    'api_path' => 'api',

    /*
     * Your API domain. By default, app domain is used. This is also a part of the default API routes
     * matcher, so when implementing your own, make sure you use this config if needed.
     */
    'api_domain' => null,

    /*
     * The path where your OpenAPI specification will be exported.
     */
    'export_path' => 'docs/openapi.json',

    /*
     * Cache configuration for the generated OpenAPI document.
     *
     * Use `scramble:cache` to warm the cache and `scramble:clear` to invalidate it.
     */
    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],

    /*
     * OpenAPI version of the resulting document.
     */
    // Guarded: Scramble is a dev-only package, so this file must load even when it is not installed.
    'openapi_version' => enum_exists(OpenApiVersion::class) ? OpenApiVersion::V3_1 : null,

    'info' => [
        /*
         * API version.
         */
        'version' => '1.0.0',

        /*
         * Description rendered on the home page of the API documentation (`/docs/api`).
         */
        'description' => <<<'MD'
Admin API for **MovieMov** (moviemov.id): post and manage movies and categories, read broken-video reports.
Everything public on the site can also be read here without a token.

## Basics

- Send `Accept: application/json` on every request, so errors come back as JSON instead of redirects.
- Write endpoints need `Authorization: Bearer <token>`. Get a token from `POST /login` (admin users only).
  Tokens do not expire; `POST /logout` revokes one.
- Movies and categories are addressed by **slug**: `/movies/night-train`, `/categories/sci-fi`.
- Responses use ISO 8601 times in UTC. A `published_at` you send **without an offset is read as WIB**
  (`Asia/Jakarta`, UTC+7).

## Post a movie

1. `POST /login` with `email` + `password`, copy `token`.
2. `POST /movies` with at least `title` and `embed_url`. `embed_url` may be the player URL or the whole
   `<iframe src="...">` code from the video host; `https://vidara.to/v/<id>` is turned into `https://vidara.to/e/<id>`.
3. Optional: `backup_embed_urls` (Server 2..6), `thumbnail` (file, multipart) or `thumbnail_url`, `categories`
   (names; new ones are created), `synopsis`, `release_year`, `duration_minutes`.
4. Publishing: leave `status` out to publish now, send `"status": "draft"` to hide it, or
   `"status": "scheduled"` with `published_at` to make it go live by itself later.

```sh
TOKEN=$(curl -s -X POST https://moviemov.id/api/login -H 'Accept: application/json' \
  -d email=admin@moviemov.id -d password=YOUR_PASSWORD | jq -r .token)

# JSON
curl -X POST https://moviemov.id/api/movies \
  -H 'Accept: application/json' -H 'Content-Type: application/json' -H "Authorization: Bearer $TOKEN" \
  -d '{"title":"Night Train","embed_url":"<iframe src=\"https://vidara.to/e/XXXX\" allowfullscreen></iframe>",
       "categories":["Drama","Thriller"],"duration_minutes":104,"release_year":2024}'

# Multipart, with a thumbnail file (use --form-string for iframe code: curl reads a -F value starting with < as a file)
curl -X POST https://moviemov.id/api/movies -H 'Accept: application/json' -H "Authorization: Bearer $TOKEN" \
  -F title='Night Train' --form-string embed_url='<iframe src="https://vidara.to/e/XXXX"></iframe>' \
  -F categories='Drama, Thriller' -F thumbnail=@night-train.jpg
```

To update a movie with a new thumbnail file use `POST /movies/{slug}` (multipart); for JSON use `PATCH`.

## Errors

`401` no/invalid token · `403` not an admin · `404` unknown slug (or a draft without an admin token) ·
`422` validation failed (`errors` lists each field) · `429` too many login attempts (5 per minute).
MD,
    ],

    'ui' => [
        'title' => 'MovieMov API',
    ],

    /*
     * Load Scramble's development tools on documentation pages. An explicit
     * SCRAMBLE_DEV_TOOLS value takes precedence over APP_DEBUG.
     */
    'dev_tools' => [
        'enabled' => env('SCRAMBLE_DEV_TOOLS', env('APP_DEBUG', false)),
    ],

    'renderer' => 'elements',

    'renderers' => [
        /*
         * Stoplight Elements config options: https://docs.stoplight.io/docs/elements/b074dc47b2826-elements-configuration-options
         */
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
        /*
         * Scalar API reference config options: https://scalar.com/products/api-references/configuration
         */
        'scalar' => [
            'view' => 'scramble::scalar',
            'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
            'theme' => 'laravel',
            'proxyUrl' => 'https://proxy.scalar.com',
            'darkMode' => false,
            'showDeveloperTools' => 'never',
            'agent' => ['disabled' => true],
            'credentials' => 'include',
        ],
        'fabric' => [
            'view' => 'scramble::fabric',
        ],
    ],

    /*
     * The list of servers of the API. By default, when `null`, server URL will be created from
     * `scramble.api_path` and `scramble.api_domain` config variables. When providing an array, you
     * will need to specify the local server URL manually (if needed).
     *
     * Example of non-default config (final URLs are generated using Laravel `url` helper):
     *
     * ```php
     * 'servers' => [
     *     'Live' => 'api',
     *     'Prod' => 'https://scramble.dedoc.co/api',
     * ],
     * ```
     */
    'servers' => [
        'Production' => 'https://moviemov.id/api',
        'Local (docker compose)' => 'http://localhost:8000/api',
    ],

    /**
     * Determines how Scramble stores the descriptions of enum cases.
     * Available options:
     * - 'description' – Case descriptions are stored as the enum schema's description using table formatting.
     * - 'extension' – Case descriptions are stored in the `x-enumDescriptions` enum schema extension.
     *
     *    @see https://redocly.com/docs-legacy/api-reference-docs/specification-extensions/x-enum-descriptions
     * - false - Case descriptions are ignored.
     */
    'enum_cases_description_strategy' => 'description',

    /**
     * Determines how Scramble stores the names of enum cases.
     * Available options:
     * - 'names' – Case names are stored in the `x-enumNames` enum schema extension.
     * - 'varnames' - Case names are stored in the `x-enum-varnames` enum schema extension.
     * - false - Case names are not stored.
     */
    'enum_cases_names_strategy' => false,

    /**
     * When Scramble encounters deep objects in query parameters, it flattens the parameters so the generated
     * OpenAPI document correctly describes the API. Flattening deep query parameters is relevant until
     * OpenAPI 3.2 is released and query string structure can be described properly.
     *
     * For example, this nested validation rule describes the object with `bar` property:
     * `['foo.bar' => ['required', 'int']]`.
     *
     * When `flatten_deep_query_parameters` is `true`, Scramble will document the parameter like so:
     * `{"name":"foo[bar]", "schema":{"type":"int"}, "required":true}`.
     *
     * When `flatten_deep_query_parameters` is `false`, Scramble will document the parameter like so:
     *  `{"name":"foo", "schema": {"type":"object", "properties":{"bar":{"type": "int"}}, "required": ["bar"]}, "required":true}`.
     */
    'flatten_deep_query_parameters' => true,

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],

    /*
     * Automatically document API security (OpenAPI `security` / `securitySchemes`) based on route
     * middleware.
     *
     * Disabled by default. Uncomment the line below to enable `MiddlewareAuthSecurityStrategy`.
     * When at least one documented route uses middleware matching the configured patterns (by default
     * `auth` and `auth:*`), bearer auth is applied globally. Routes without matching middleware are
     * marked as public (`security: []`).
     *
     * Set to `null` explicitly to disable. If you already configure security manually via
     * `afterOpenApiGenerated` / `extendOpenApi`, keep this disabled to avoid duplicate schemes.
     *
     * Customize with a class-string or [class, options]:
     *
     * 'security_strategy' => [
     *     \Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy::class,
     *     [
     *         'middleware' => ['auth', 'auth:*'],
     *         'scheme' => \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer'),
     *     ],
     * ],
     */
    // 'security_strategy' => \Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy::class,
    'security_strategy' => MiddlewareAuthSecurityStrategy::class,
];
