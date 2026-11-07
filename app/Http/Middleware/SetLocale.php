<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sets the app locale for a route group: `->middleware('locale:en')`. */
class SetLocale
{
    public function handle(Request $request, Closure $next, string $locale = 'id'): Response
    {
        if (array_key_exists($locale, site_locales())) {
            app()->setLocale($locale);
        }

        $response = $next($request);
        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }
}
