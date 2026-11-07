<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Movie;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = collect([[
            'loc' => route('movies.index'),
            'lastmod' => Movie::max('updated_at'),
            'changefreq' => 'daily',
            'priority' => '1.0',
        ]]);

        Category::query()->whereHas('movies')->orderBy('name')
            ->withMax('movies', 'movies.updated_at')
            ->get()
            ->each(fn (Category $c) => $urls->push([
                'loc' => route('movies.category', $c),
                'lastmod' => $c->movies_max_moviesupdated_at,
                'changefreq' => 'daily',
                'priority' => '0.8',
            ]));

        Movie::query()->latest('id')->limit(45000)->get(['slug', 'updated_at'])
            ->each(fn (Movie $m) => $urls->push([
                'loc' => route('movies.show', $m),
                'lastmod' => $m->updated_at,
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ]));

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
            '',
            'Sitemap: '.route('sitemap'),
            '',
        ]);

        return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
