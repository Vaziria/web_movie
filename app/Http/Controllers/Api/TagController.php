<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Tags', 'Free keywords on movies (actor, country, mood ...) with pages at `/tag/{slug}`. Tags are also created automatically from a movie\'s `tags` field. `movies_count` counts published movies.', weight: 3)]
class TagController extends Controller
{
    /**
     * List tags
     *
     * Tags used by at least one published movie, most used first. With an admin token, `all=1` also lists unused
     * tags.
     *
     * @unauthenticated
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            /** Admin token only: `1` also lists tags without published movies. */
            'all' => ['sometimes', 'boolean'],
        ]);

        $all = $request->boolean('all') && $request->user('sanctum')?->is_admin;
        $tags = Tag::query()
            ->when(! $all, fn ($q) => $q->whereHas('movies', fn ($m) => $m->published()))
            ->withCount(self::publishedCount())
            ->orderByDesc('movies_count')->orderBy('name')
            ->get()
            ->map(fn (Tag $t) => $this->present($t));

        return response()->json(['data' => $tags]);
    }

    /**
     * Get a tag
     *
     * @unauthenticated
     */
    public function show(Tag $tag): JsonResponse
    {
        return response()->json(['data' => $this->present($tag->loadCount(self::publishedCount()))]);
    }

    /**
     * Create a tag
     *
     * `slug` is generated from `name` when left out. Usually not needed: posting a movie with a new tag name
     * creates the tag.
     */
    public function store(TagRequest $request): JsonResponse
    {
        $tag = Tag::create($request->validated());

        return response()->json(['data' => $this->present($tag->loadCount(self::publishedCount()))], 201);
    }

    /**
     * Update a tag
     *
     * Rename it or change its `slug` (this changes the tag page URL).
     */
    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse
    {
        $tag->update($request->validated());

        return response()->json(['data' => $this->present($tag->loadCount(self::publishedCount()))]);
    }

    /**
     * Delete a tag
     *
     * The tag is removed from its movies; the movies themselves are kept.
     */
    public function destroy(Request $request, Tag $tag): Response
    {
        abort_unless($request->user()?->is_admin, 403);
        $tag->delete();

        return response()->noContent();
    }

    private static function publishedCount(): array
    {
        return ['movies' => fn ($q) => $q->published()];
    }

    /** @return array{id: int, name: string, slug: string, movies_count: int, url: string} */
    private function present(Tag $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'slug' => $t->slug,
            'movies_count' => (int) $t->movies_count,
            'url' => route('movies.tag', $t),
        ];
    }
}
