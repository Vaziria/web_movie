<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovieRequest;
use App\Http\Resources\MovieResource;
use App\Models\Movie;
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
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $movies = Movie::query()
            ->browse($request->string('q')->toString(), $request->string('sort')->toString())
            ->paginate($request->integer('per_page', 24))
            ->withQueryString();

        return MovieResource::collection($movies);
    }

    public function show(Movie $movie): MovieResource
    {
        return new MovieResource($movie);
    }

    public function store(MovieRequest $request): JsonResponse
    {
        $movie = new Movie($request->validated());
        $movie->user()->associate($request->user());
        $movie->save();

        return (new MovieResource($movie->refresh()))->response()->setStatusCode(201);
    }

    public function update(MovieRequest $request, Movie $movie): MovieResource
    {
        $movie->update($request->validated());

        return new MovieResource($movie);
    }

    public function destroy(Request $request, Movie $movie): Response
    {
        abort_unless($request->user()?->is_admin, 403);
        $movie->delete();

        return response()->noContent();
    }
}
