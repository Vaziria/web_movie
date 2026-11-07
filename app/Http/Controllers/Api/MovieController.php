<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovieRequest;
use App\Http\Resources\MovieResource;
use App\Models\Category;
use App\Models\Movie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MovieController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort' => ['sometimes', 'nullable', 'in:'.implode(',', array_keys(Movie::SORTS))],
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $movies = Movie::query()
            ->with('categories')
            ->browse($request->string('q')->toString(), $request->string('sort')->toString(), $request->string('category')->toString())
            ->paginate($request->integer('per_page', 24))
            ->withQueryString();

        return MovieResource::collection($movies);
    }

    public function show(Movie $movie): MovieResource
    {
        return new MovieResource($movie->load('categories'));
    }

    public function store(MovieRequest $request): JsonResponse
    {
        $movie = DB::transaction(function () use ($request) {
            $movie = new Movie($this->attributes($request));
            $movie->user()->associate($request->user());
            $movie->save();
            $this->syncCategories($request, $movie);

            return $movie;
        });

        return (new MovieResource($movie->refresh()->load('categories')))->response()->setStatusCode(201);
    }

    public function update(MovieRequest $request, Movie $movie): MovieResource
    {
        DB::transaction(function () use ($request, $movie) {
            $movie->update($this->attributes($request));
            $this->syncCategories($request, $movie);
        });

        return new MovieResource($movie->refresh()->load('categories'));
    }

    public function destroy(Request $request, Movie $movie): Response
    {
        abort_unless($request->user()?->is_admin, 403);
        $movie->delete();

        return response()->noContent();
    }

    /** Validated movie columns, with an uploaded thumbnail file turned into its public path. */
    private function attributes(MovieRequest $request): array
    {
        $data = collect($request->validated())->except(['thumbnail', 'categories'])->all();

        if ($request->file('thumbnail') instanceof UploadedFile) {
            $data['thumbnail_url'] = $this->storeThumbnail($request->file('thumbnail'));
        }

        return $data;
    }

    private function storeThumbnail(UploadedFile $file): string
    {
        $dir = public_path(Movie::THUMBNAIL_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = Str::random(32).'.'.($file->guessExtension() ?: 'jpg');
        $file->move($dir, $name);

        return '/'.Movie::THUMBNAIL_DIR.'/'.$name;
    }

    private function syncCategories(MovieRequest $request, Movie $movie): void
    {
        if ($request->has('categories')) {
            $movie->categories()->sync(Category::idsForNames($request->validated('categories', [])));
        }
    }
}
