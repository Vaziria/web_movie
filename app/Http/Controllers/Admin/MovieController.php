<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovieRequest;
use App\Models\Category;
use App\Models\Movie;
use App\Models\Tag;
use App\Services\MovieWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MovieController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $category = (string) $request->query('category', '');   // a slug, "none" (no category) or "" (all)
        $status = in_array($request->query('status'), ['published', 'scheduled', 'draft'], true) ? (string) $request->query('status') : '';
        $tag = Tag::query()->where('slug', (string) $request->query('tag', ''))->first();   // from the Tags page
        $statusCounts = [
            'scheduled' => Movie::query()->status('scheduled')->count(),
            'draft' => Movie::query()->status('draft')->count(),
        ];
        $categories = Category::query()->withCount('movies')->orderBy('name')->get(['id', 'name', 'slug']);

        $movies = Movie::query()->with('categories', 'tags')
            ->withCount(['reports as open_reports_count' => fn ($r) => $r->whereNull('resolved_at')])
            ->when($category === 'none', fn ($m) => $m->whereDoesntHave('categories'))
            ->when($status !== '', fn ($m) => $m->status($status))
            ->browse($q, 'created', $category !== 'none' ? $category : null, null, $tag ? [$tag->slug] : [])
            ->reorder()->orderByDesc('created_at')->orderByDesc('id')   // admin: most recently added first, drafts included
            ->paginate(20)->withQueryString();

        return view('admin.movies.index', compact('movies', 'q', 'category', 'categories', 'status', 'statusCounts', 'tag'));
    }

    public function create(): View
    {
        return view('admin.movies.form', ['movie' => new Movie] + $this->formLists());
    }

    public function store(MovieRequest $request, MovieWriter $writer): RedirectResponse
    {
        $movie = $writer->save($request, null, $request->user());

        return redirect()->route('admin.movies.edit', $movie)->with('status', "Created \"{$movie->title}\".");
    }

    public function edit(Movie $movie): View
    {
        return view('admin.movies.form', ['movie' => $movie->load('categories', 'tags')] + $this->formLists());
    }

    /** Existing categories and the most used tags, shown as hints under the inputs. */
    private function formLists(): array
    {
        return [
            'allCategories' => Category::orderBy('name')->pluck('name'),
            'popularTags' => Tag::withCount('movies')->orderByDesc('movies_count')->orderBy('name')->limit(30)->pluck('name'),
        ];
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
