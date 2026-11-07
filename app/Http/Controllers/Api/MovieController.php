<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovieRequest;
use App\Http\Requests\UpdateMovieRequest;
use App\Http\Resources\MovieResource;
use App\Models\Movie;
use App\Services\MovieWriter;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Movies', 'Movie posts: list, read, create, update, delete. Write endpoints need an admin token.', weight: 1)]
class MovieController extends Controller
{
    /**
     * List movies
     *
     * Public list of published movies, 24 per page. Filters combine freely (`q` + `category` + `sort` + `dir`).
     * With an admin token, `status` also lists drafts and scheduled movies.
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            /**
             * Search in title and synopsis.
             *
             * @example night
             */
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            /**
             * `created` = newest by publish time (default), `trending` = most views in the last 7 days (adds
             * `views_week`), `rating` = highest average, `views` = most viewed all time, `duration` = longest.
             */
            'sort' => ['sometimes', 'nullable', 'in:'.implode(',', array_keys(Movie::SORTS))],
            /**
             * Category slug.
             *
             * @example sci-fi
             */
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
            /**
             * Reverse the sort with `asc` (e.g. `sort=duration&dir=asc` = shortest first). Default `desc`.
             * Movies without a value (e.g. no duration) are always last.
             */
            'dir' => ['sometimes', 'nullable', 'in:asc,desc'],
            /**
             * Results per page.
             *
             * @default 24
             */
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            /**
             * Admin token only: `scheduled`, `draft` or `all`. Anything but `published` without an admin token
             * returns 403.
             *
             * @default published
             */
            'status' => ['sometimes', 'nullable', 'in:published,scheduled,draft,all'],
        ]);

        // Drafts and scheduled movies are only listed for an admin token.
        $status = $request->string('status')->toString() ?: 'published';
        abort_if($status !== 'published' && ! $request->user('sanctum')?->is_admin, 403, 'Only admins can list drafts and scheduled movies.');

        $movies = Movie::query()
            ->with('categories')
            ->status($status)
            ->browse($request->string('q')->toString(), $request->string('sort')->toString(), $request->string('category')->toString(), $request->string('dir')->toString() ?: null)
            ->paginate($request->integer('per_page', 24))
            ->withQueryString();

        return MovieResource::collection($movies);
    }

    /**
     * Get a movie
     *
     * Drafts and scheduled movies return 404 unless you send an admin token.
     *
     * @unauthenticated
     */
    public function show(Request $request, Movie $movie): MovieResource
    {
        abort_unless($movie->isPublished() || $request->user('sanctum')?->is_admin, 404);

        return new MovieResource($movie->load('categories'));
    }

    /**
     * Create a movie
     *
     * Send JSON or multipart (multipart is needed to upload a `thumbnail` file). Only `title` and `embed_url` are
     * required; `embed_url` may be the whole `<iframe>` code. New movies are published right away unless you send
     * `status` = `draft` or `scheduled` (+ `published_at`, read as WIB without an offset).
     *
     * With curl multipart, send iframe code with `--form-string embed_url='<iframe ...>'`: curl reads a `-F` value
     * starting with `<` as a file name.
     *
     * @status 201
     */
    public function store(MovieRequest $request, MovieWriter $writer): JsonResponse
    {
        $movie = $writer->save($request, null, $request->user());

        return (new MovieResource($movie))->response()->setStatusCode(201);
    }

    /**
     * Update a movie
     *
     * Same fields as create, all optional: only the fields you send change. Use `PATCH`/`PUT` with JSON, or
     * `POST` with multipart to upload a new `thumbnail` file (PHP only reads uploaded files on POST).
     * `thumbnail_url: null` or `remove_thumbnail: true` removes the thumbnail.
     */
    public function update(UpdateMovieRequest $request, Movie $movie, MovieWriter $writer): MovieResource
    {
        return new MovieResource($writer->save($request, $movie));
    }

    /**
     * Delete a movie
     *
     * Also deletes its ratings, reports and uploaded thumbnail file.
     */
    public function destroy(Request $request, Movie $movie): Response
    {
        abort_unless($request->user()?->is_admin, 403);
        $movie->delete();

        return response()->noContent();
    }
}
