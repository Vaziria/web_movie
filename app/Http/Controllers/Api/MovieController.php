<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovieRequest;
use App\Http\Resources\MovieResource;
use App\Models\Movie;
use App\Services\MovieWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MovieController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort' => ['sometimes', 'nullable', 'in:'.implode(',', array_keys(Movie::SORTS))],
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'dir' => ['sometimes', 'nullable', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $movies = Movie::query()
            ->with('categories')
            ->browse($request->string('q')->toString(), $request->string('sort')->toString(), $request->string('category')->toString(), $request->string('dir')->toString() ?: null)
            ->paginate($request->integer('per_page', 24))
            ->withQueryString();

        return MovieResource::collection($movies);
    }

    public function show(Movie $movie): MovieResource
    {
        return new MovieResource($movie->load('categories'));
    }

    public function store(MovieRequest $request, MovieWriter $writer): JsonResponse
    {
        $movie = $writer->save($request, null, $request->user());

        return (new MovieResource($movie))->response()->setStatusCode(201);
    }

    public function update(MovieRequest $request, Movie $movie, MovieWriter $writer): MovieResource
    {
        return new MovieResource($writer->save($request, $movie));
    }

    public function destroy(Request $request, Movie $movie): Response
    {
        abort_unless($request->user()?->is_admin, 403);
        $movie->delete();

        return response()->noContent();
    }
}
