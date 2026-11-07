<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Movie;
use App\Models\PlayerReport;
use App\Services\RatingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MovieController extends Controller
{
    public const VOTER_COOKIE = 'voter';

    /** Sort key => English label (translated in views with __()). */
    public const SORT_LABELS = ['created' => 'Newest', 'trending' => 'Trending', 'rating' => 'Rating', 'views' => 'Most viewed', 'duration' => 'Duration'];

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
        $defaultDir = Movie::SORTS[$sort][1];
        $dir = in_array($request->query('dir'), ['asc', 'desc'], true) ? (string) $request->query('dir') : $defaultDir;

        $movies = Movie::query()
            ->published()
            ->browse($q, $sort, $category?->slug, $dir)
            ->paginate(24)
            ->withQueryString();

        $categories = Category::query()->whereHas('movies', fn ($m) => $m->published())->orderBy('name')->get(['id', 'name', 'slug']);

        // Home page only (no filter, default sort, page 1): "Trending this week" and "Top rated" rows above the grid.
        $sections = [];
        if (! $category && $q === '' && $sort === 'created' && $dir === $defaultDir && $page === 1) {
            $sections = $this->homeSections();
        }

        $name = config('app.name');
        $heading = $category
            ? __(':name movies', ['name' => $category->name])
            : ($q !== '' ? __('Search: :q', ['q' => $q]) : ($sort === 'created' ? __('Latest movies') : __('All movies')));

        // Sort variants duplicate the main list, so they all point at it; search results are not indexed.
        $urlIn = function (string $locale) use ($category, $page) {
            $base = $category ? lroute('movies.category', $category, $locale) : lroute('movies.index', [], $locale);

            return $page > 1 ? $base.'?page='.$page : $base;
        };

        $seo = [
            'title' => $category
                ? __(':name Movies - Watch Online | :site', ['name' => $category->name, 'site' => $name])
                : ($q !== '' ? __('Search ":q" | :site', ['q' => $q, 'site' => $name]) : __(':site - Watch Movies Online', ['site' => $name])),
            'description' => $category
                ? ($category->description
                    ? Str::limit(trim(preg_replace('/\s+/', ' ', $category->description)), 155)
                    : __('Watch :name movies online on :site. Browse the newest, top rated and most viewed :name titles.', ['name' => $category->name, 'site' => $name]))
                : __('Watch movies online on :site. Browse the newest, top rated and most viewed movies by category.', ['site' => $name]),
            'canonical' => $urlIn(app()->getLocale()),
            'alternates' => $q === '' ? $this->alternates($urlIn) : [],
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
                'url' => lroute('movies.show', $m),
                'name' => $m->title,
            ])->all(),
        ];

        if (! $category && $q === '' && $page === 1) {
            $seo['jsonld_site'] = [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $name,
                'url' => lroute('movies.index'),
                'inLanguage' => app()->getLocale(),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => rtrim(lroute('movies.index'), '/').'/?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ];
        }

        return view('movies.index', compact('movies', 'categories', 'category', 'q', 'sort', 'dir', 'defaultDir', 'heading', 'seo', 'sections'));
    }

    /** @return array<string, array{title: string, url: string, movies: Collection, ranked: bool}> */
    private function homeSections(): array
    {
        $trending = Movie::query()->published()->withWeekViews()
            ->orderByDesc('week_views')->orderByDesc('views')->orderByDesc('id')
            ->limit(10)->get()
            ->filter(fn (Movie $m) => $m->week_views > 0)->values();

        $topRated = Movie::query()->published()->where('rating_count', '>', 0)
            ->orderByDesc('rating_avg')->orderByDesc('rating_count')->orderByDesc('id')
            ->limit(10)->get();

        $home = lroute('movies.index');

        return array_filter([
            'trending' => $trending->isEmpty() ? null : [
                'title' => __('Trending this week'), 'url' => $home.'?sort=trending', 'movies' => $trending, 'ranked' => true,
            ],
            'rating' => $topRated->isEmpty() ? null : [
                'title' => __('Top rated'), 'url' => $home.'?sort=rating', 'movies' => $topRated, 'ranked' => false,
            ],
        ]);
    }

    public function show(Request $request, Movie $movie, RatingService $ratings): View
    {
        // Drafts and scheduled movies: 404 for visitors, a noindex preview for a logged-in admin.
        $preview = ! $movie->isPublished();
        abort_if($preview && ! $request->user()?->is_admin, 404);

        // Count one view per movie per session (shared by both languages). Previews are not counted.
        $key = 'viewed.'.$movie->id;
        if (! $preview && ! $request->session()->has($key)) {
            DB::table('movies')->where('id', $movie->id)->increment('views');
            $movie->recordDailyView();
            $movie->views++;
            $request->session()->put($key, true);
        }

        $players = $movie->players();
        $server = (int) $request->query('server', 1);
        if (! isset($players[$server])) {
            $server = 1;
        }

        $movie->load('categories');
        $related = $this->related($movie);
        $myScore = $ratings->scoreFor($movie, $request->cookie(self::VOTER_COOKIE));
        $url = lroute('movies.show', $movie);
        $name = config('app.name');
        $thumb = $movie->thumbnailSrc();
        $duration = $movie->duration_minutes ? 'PT'.$movie->duration_minutes.'M' : null;

        $video = array_filter([
            '@type' => 'VideoObject',
            'name' => $movie->title,
            'description' => $movie->summary(500),
            'thumbnailUrl' => $thumb,
            'uploadDate' => ($movie->published_at ?? $movie->created_at)?->toIso8601String(),
            'embedUrl' => $movie->embed_url,
            'duration' => $duration,
            'inLanguage' => app()->getLocale(),
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

        $crumbs = [['name' => __('Home'), 'item' => lroute('movies.index')]];
        if ($first = $movie->categories->first()) {
            $crumbs[] = ['name' => $first->name, 'item' => lroute('movies.category', $first)];
        }
        $crumbs[] = ['name' => $movie->title, 'item' => $url];

        $breadcrumbs = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)
                ->map(fn ($b, $i) => ['@type' => 'ListItem', 'position' => $i + 1] + $b)
                ->all(),
        ];

        $title = $movie->title.($movie->release_year ? " ({$movie->release_year})" : '');
        $seo = [
            'title' => __(':title - Watch Online | :site', ['title' => $title, 'site' => $name]),
            'description' => $movie->summary(),
            'canonical' => $url,
            'alternates' => $this->alternates(fn ($l) => lroute('movies.show', $movie, $l)),
            'robots' => $preview ? 'noindex, nofollow' : 'index, follow, max-image-preview:large',
            'type' => 'video.movie',
            'image' => $thumb,
            'jsonld' => $schema,
            'jsonld_site' => $breadcrumbs,
        ];

        return view('movies.show', compact('movie', 'myScore', 'seo', 'related', 'players', 'server', 'preview'));
    }

    /** hreflang map: each locale's URL plus x-default (Indonesian). */
    private function alternates(callable $urlIn): array
    {
        $map = [];
        foreach (array_keys(site_locales()) as $locale) {
            $map[$locale] = $urlIn($locale);
        }
        $map['x-default'] = $map['id'];

        return $map;
    }

    /** Up to 8 movies sharing a category (best rated, then most viewed), topped up with the newest ones. */
    private function related(Movie $movie, int $limit = 8): Collection
    {
        $ids = $movie->categories->pluck('id');
        $related = $ids->isEmpty() ? collect() : Movie::query()->published()
            ->whereKeyNot($movie->id)
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $ids))
            ->orderByDesc('rating_avg')->orderByDesc('views')->orderByDesc('id')
            ->limit($limit)->get();

        if ($related->count() < $limit) {
            $related = $related->concat(Movie::query()->published()
                ->whereKeyNot($movie->id)
                ->whereNotIn('id', $related->pluck('id'))
                ->latest('id')->limit($limit - $related->count())->get());
        }

        return $related;
    }

    public function rate(Request $request, Movie $movie, RatingService $ratings): RedirectResponse
    {
        abort_unless($movie->isPublished(), 404);

        $data = $request->validate([
            'score' => ['required', 'integer', 'between:'.Movie::MIN_SCORE.','.Movie::MAX_SCORE],
        ], [
            'score.*' => __('Choose a rating from :min to :max stars.', ['min' => Movie::MIN_SCORE, 'max' => Movie::MAX_SCORE]),
        ]);

        $voter = $request->cookie(self::VOTER_COOKIE);
        if (! is_string($voter) || strlen($voter) < 32) {
            $voter = Str::random(40);
        }

        $ratings->rate($movie, $voter, (string) $request->ip(), (int) $data['score']);

        return redirect()->to(lroute('movies.show', $movie))
            ->with('status', __('Thanks for rating!'))
            ->withCookie(Cookie::forever(self::VOTER_COOKIE, $voter));
    }

    /** Visitor reports a broken player. One open report per IP, movie and server per 24 hours. */
    public function report(Request $request, Movie $movie): RedirectResponse
    {
        abort_unless($movie->isPublished(), 404);

        $data = $request->validate([
            'server' => ['required', 'integer', 'between:1,'.count($movie->players())],
            'reason' => ['sometimes', 'in:'.implode(',', PlayerReport::REASONS)],
        ]);

        $ipHash = hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
        $server = (int) $data['server'];

        $duplicate = PlayerReport::open()
            ->where('movie_id', $movie->id)->where('server', $server)->where('ip_hash', $ipHash)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if (! $duplicate) {
            PlayerReport::create([
                'movie_id' => $movie->id,
                'server' => $server,
                'reason' => $data['reason'] ?? 'not_loading',
                'ip_hash' => $ipHash,
            ]);
        }

        $url = lroute('movies.show', $movie).($server > 1 ? '?server='.$server : '');

        return redirect()->to($url)->with('status', __('Thanks! We will check the video soon. Try another server meanwhile.'));
    }
}
