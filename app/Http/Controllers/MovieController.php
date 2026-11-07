<?php

namespace App\Http\Controllers;

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

    public function index(Request $request): View
    {
        $q = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $sort = array_key_exists((string) $request->query('sort'), Movie::SORTS) ? $request->query('sort') : 'created';

        $movies = Movie::query()->browse($q, $sort)->paginate(24)->withQueryString();

        return view('movies.index', compact('movies', 'q', 'sort'));
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

        $myScore = $ratings->scoreFor($movie, $request->cookie(self::VOTER_COOKIE));

        return view('movies.show', compact('movie', 'myScore'));
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
