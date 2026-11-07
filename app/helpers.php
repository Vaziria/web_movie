<?php

/*
 * Locale helpers for the public site. Indonesian ("id") lives at /, English ("en") under /en.
 * Route names: "movies.show" (id) and "en.movies.show" (en).
 */

use Illuminate\Support\Facades\Route;

if (! function_exists('site_locales')) {
    /** @return array<string, array{name: string, og: string, prefix: string}> */
    function site_locales(): array
    {
        return [
            'id' => ['name' => 'Bahasa Indonesia', 'og' => 'id_ID', 'prefix' => ''],
            'en' => ['name' => 'English', 'og' => 'en_US', 'prefix' => 'en'],
        ];
    }
}

if (! function_exists('lroute')) {
    /** URL of a public route in the given (default: current) locale. */
    function lroute(string $name, mixed $parameters = [], ?string $locale = null, bool $absolute = true): string
    {
        $locale ??= app()->getLocale();
        $name = preg_replace('/^en\./', '', $name);

        return route(($locale === 'en' ? 'en.' : '').$name, $parameters, $absolute);
    }
}

if (! function_exists('current_url_in')) {
    /** The current public page in another locale (same route, parameters and query). */
    function current_url_in(string $locale, bool $withQuery = true): string
    {
        $route = Route::current();
        if (! $route || ! $route->getName()) {
            return lroute('movies.index', [], $locale);
        }

        $url = lroute($route->getName(), $route->parameters(), $locale);
        $query = $withQuery ? request()->getQueryString() : null;

        return $query ? $url.'?'.$query : $url;
    }
}

if (! function_exists('nf')) {
    /** Locale-aware number format: 1.234,5 (id) / 1,234.5 (en). */
    function nf(int|float|null $number, int $decimals = 0): string
    {
        return app()->getLocale() === 'id'
            ? number_format((float) $number, $decimals, ',', '.')
            : number_format((float) $number, $decimals);
    }
}
