<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Movie;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    /** Every public page in every language, each entry listing its hreflang alternates. */
    public function sitemap(): Response
    {
        $pages = collect([[
            'route' => 'movies.index', 'params' => [],
            'lastmod' => Movie::max('updated_at'), 'changefreq' => 'daily', 'priority' => '1.0',
        ]]);

        Category::query()->whereHas('movies')->orderBy('name')
            ->withMax('movies', 'movies.updated_at')
            ->get()
            ->each(fn (Category $c) => $pages->push([
                'route' => 'movies.category', 'params' => $c,
                'lastmod' => $c->movies_max_moviesupdated_at, 'changefreq' => 'daily', 'priority' => '0.8',
            ]));

        Movie::query()->latest('id')->limit(20000)->get(['slug', 'updated_at'])
            ->each(fn (Movie $m) => $pages->push([
                'route' => 'movies.show', 'params' => $m,
                'lastmod' => $m->updated_at, 'changefreq' => 'weekly', 'priority' => '0.6',
            ]));

        $locales = array_keys(site_locales());
        $urls = $pages->flatMap(function (array $p) use ($locales) {
            $alternates = [];
            foreach ($locales as $l) {
                $alternates[$l] = lroute($p['route'], $p['params'], $l);
            }
            $alternates['x-default'] = $alternates['id'];

            return collect($locales)->map(fn ($l) => [
                'loc' => $alternates[$l],
                'alternates' => $alternates,
                'lastmod' => $p['lastmod'],
                'changefreq' => $p['changefreq'],
                'priority' => $p['priority'],
            ]);
        });

        return response()
            ->view('seo.sitemap', ['urls' => $urls], 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $body = implode("\n", [
            'User-agent: *',
            'Disallow: /api/',
            'Disallow: /admin',
            'Disallow: /*?q=',
            'Disallow: /*?server=',
            '',
            'Sitemap: '.route('sitemap'),
            '',
        ]);

        return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
