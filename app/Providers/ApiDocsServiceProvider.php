<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Illuminate\Support\ServiceProvider;

/**
 * Tweaks for the generated OpenAPI document (docs/openapi.json). Scramble is a dev-only package, so this provider
 * does nothing on production, where it is not installed.
 */
class ApiDocsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! class_exists(Scramble::class)) {
            return;
        }

        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi) {
            foreach ($openApi->paths as $path) {
                foreach ($path->operations as $operation) {
                    $body = $operation->requestBodyObject;
                    if (! $body || ! isset($body->content['multipart/form-data']) || isset($body->content['application/json'])) {
                        continue;
                    }
                    // Endpoints with a file field are inferred as multipart only, but they take JSON just as well.
                    $body->content = ['application/json' => $body->content['multipart/form-data']] + $body->content;
                }
            }
        });
    }
}
