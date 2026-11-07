<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Movie;
use App\Services\RatingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MovieController extends Controller
{
    public const VOTER_COOKIE = 'voter';

    public const SORT_LABELS = ['created' => 'Newest', 'rating' => 'Top rated', 'views' => 'Most viewed'];

    public function index(Request $request): View
    {
        return $this->listing($request, null);
    }

    public function category(Request $request, Category $category): View
    {
        return $this->listing($request, $category);
    }

    private function listing(Request $request, ?Category $category): View
    {
        $q = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $sort = array_key_exists((string) $request->query('sort'), Movie::SORTS) ? (string) $request->query('sort') : 'created';
        $page = max(1, (int) $request->query('page', 1));

        $movies = Movie::query()
            ->browse($q, $sort, $category?->slug)
            ->paginate(24)
            ->withQueryString();

        $categories = Category::query()->whereHas('movies')->orderBy('name')->get(['id', 'name', 'slug']);

        $base = $category ? route('movies.category', $category) : route('movies.index');
        $name = config('app.name');
        $heading = $category ? $category->name.' movies' : ($q !== '' ? "Search: {$q}" : 'Latest movies');

        $seo = [
            'title' => $category
                ? "{$category->name} Movies - Watch Online | {$name}"
                : ($q !== '' ? "Search \"{$q}\" | {$name}" : "{$name} - Watch Movies Online"),
            'description' => $category
                ? ($category->description ? Str::limit(trim(preg_replace('/\s+/', ' ', $category->description)), 155) : "Watch {$category->name} movies online on {$name}. Browse the newest, top rated and most viewed {$category->name} titles.")
                : "Watch movies online on {$name}. Browse the newest, top rated and most viewed movies by category.",
            // Sort variants duplicate the main list, so they all point at it; search results are not indexed.
            'canonical' => $page > 1 ? $base.'?page='.$page : $base,
            'robots' => $q !== '' ? 'noindex, follow' : 'index, follow',
            'type' => 'website',
        ];

        $seo['jsonld'] = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $heading,
            'itemListElement' => $movies->values()->map(fn (Movie $m, int $i) => [
                '@type' => 'ListItem',
                'position' => ($movies->firstItem() ?? 1) + $i,
                'url' => route('movies.show', $m),
                'name' => $m->title,
            ])->all(),
        ];

        if (! $category && $q === '' && $page === 1) {
            $seo['jsonld_site'] = [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $name,
                'url' => route('movies.index'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => url('/').'/?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ];
        }

        return view('movies.index', compact('movies', 'categories', 'category', 'q', 'sort', 'heading', 'seo'));
    }

    public function show(Request $request, Movie $movie, RatingService $ratings): View
    {
        // Count one view per movie per session.
        $key = 'viewed.'.$movie->id;
        if (! $request->session()->has($key)) {
            DB::table('movies')->where('id', $movie->id)->increment('views');
            $movie->views++;
            $request->session()->put($key, true);
        }

        $movie->load('categories');
        $related = $this->related($movie);
        $myScore = $ratings->scoreFor($movie, $request->cookie(self::VOTER_COOKIE));
        $url = route('movies.show', $movie);
        $name = config('app.name');
        $thumb = $movie->thumbnailSrc();
        $duration = $movie->duration_minutes ? 'PT'.$movie->duration_minutes.'M' : null;

        $video = array_filter([
            '@type' => 'VideoObject',
            'name' => $movie->title,
            'description' => $movie->summary(500),
            'thumbnailUrl' => $thumb,
            'uploadDate' => $movie->created_at?->toIso8601String(),
            'embedUrl' => $movie->embed_url,
            'duration' => $duration,
            'interactionStatistic' => [
                '@type' => 'InteractionCounter',
                'interactionType' => ['@type' => 'WatchAction'],
                'userInteractionCount' => $movie->views,
            ],
        ]);

        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Movie',
            'name' => $movie->title,
            'url' => $url,
            'description' => $movie->summary(500),
            'image' => $thumb,
            'dateCreated' => $movie->release_year ? (string) $movie->release_year : null,
            'duration' => $duration,
            'genre' => $movie->categories->pluck('name')->all() ?: null,
            'aggregateRating' => $movie->rating_count > 0 ? [
                '@type' => 'AggregateRating',
                'ratingValue' => round($movie->rating_avg, 1),
                'ratingCount' => $movie->rating_count,
                'bestRating' => Movie::MAX_SCORE,
                'worstRating' => Movie::MIN_SCORE,
            ] : null,
            'video' => $video,
        ]);

        $crumbs = [['name' => 'Home', 'item' => route('movies.index')]];
        if ($first = $movie->categories->first()) {
            $crumbs[] = ['name' => $first->name, 'item' => route('movies.category', $first)];
        }
        $crumbs[] = ['name' => $movie->title, 'item' => $url];

        $breadcrumbs = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)
                ->map(fn ($b, $i) => ['@type' => 'ListItem', 'position' => $i + 1] + $b)
                ->all(),
        ];

        $seo = [
            'title' => $movie->title.($movie->release_year ? " ({$movie->release_year})" : '')." - Watch Online | {$name}",
            'description' => $movie->summary(),
            'canonical' => $url,
            'robots' => 'index, follow, max-image-preview:large',
            'type' => 'video.movie',
            'image' => $thumb,
            'jsonld' => $schema,
            'jsonld_site' => $breadcrumbs,
        ];

        return view('movies.show', compact('movie', 'myScore', 'seo', 'related'));
    }

    /** Up to 8 movies sharing a category (best rated, then most viewed), topped up with the newest ones. */
    private function related(Movie $movie, int $limit = 8)
    {
        $ids = $movie->categories->pluck('id');
        $related = $ids->isEmpty() ? collect() : Movie::query()
            ->whereKeyNot($movie->id)
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $ids))
            ->orderByDesc('rating_avg')->orderByDesc('views')->orderByDesc('id')
            ->limit($limit)->get();

        if ($related->count() < $limit) {
            $related = $related->concat(Movie::query()
                ->whereKeyNot($movie->id)
                ->whereNotIn('id', $related->pluck('id'))
                ->latest('id')->limit($limit - $related->count())->get());
        }

        return $related;
    }

    public function rate(Request $request, Movie $movie, RatingService $ratings): RedirectResponse
    {
        $data = $request->validate([
            'score' => ['required', 'integer', 'between:'.Movie::MIN_SCORE.','.Movie::MAX_SCORE],
        ]);

        $voter = $request->cookie(self::VOTER_COOKIE);
        if (! is_string($voter) || strlen($voter) < 32) {
            $voter = Str::random(40);
        }

        $ratings->rate($movie, $voter, (string) $request->ip(), (int) $data['score']);

        return redirect()->route('movies.show', $movie)
            ->with('status', 'Thanks for rating!')
            ->withCookie(Cookie::forever(self::VOTER_COOKIE, $voter));
    }
}
