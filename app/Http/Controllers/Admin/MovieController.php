<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovieRequest;
use App\Models\Category;
use App\Models\Movie;
use App\Services\MovieWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MovieController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $movies = Movie::query()->with('categories')
            ->withCount(['reports as open_reports_count' => fn ($r) => $r->whereNull('resolved_at')])
            ->browse($q, 'created')->paginate(20)->withQueryString();

        return view('admin.movies.index', compact('movies', 'q'));
    }

    public function create(): View
    {
        return view('admin.movies.form', ['movie' => new Movie, 'allCategories' => Category::orderBy('name')->pluck('name')]);
    }

    public function store(MovieRequest $request, MovieWriter $writer): RedirectResponse
    {
        $movie = $writer->save($request, null, $request->user());

        return redirect()->route('admin.movies.edit', $movie)->with('status', "Created \"{$movie->title}\".");
    }

    public function edit(Movie $movie): View
    {
        return view('admin.movies.form', ['movie' => $movie->load('categories'), 'allCategories' => Category::orderBy('name')->pluck('name')]);
    }

    public function update(MovieRequest $request, Movie $movie, MovieWriter $writer): RedirectResponse
    {
        $movie = $writer->save($request, $movie);

        return redirect()->route('admin.movies.edit', $movie)->with('status', 'Saved.');
    }

    public function destroy(Movie $movie): RedirectResponse
    {
        $title = $movie->title;
        $movie->delete();

        return redirect()->route('admin.movies.index')->with('status', "Deleted \"{$title}\".");
    }
}
