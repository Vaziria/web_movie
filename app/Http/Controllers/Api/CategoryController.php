<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Categories', 'Categories shown as chips and `/category/{slug}` pages. `movies_count` counts published movies.', weight: 2)]
class CategoryController extends Controller
{
    /**
     * List categories
     *
     * All categories, sorted by name.
     *
     * @unauthenticated
     */
    public function index(): JsonResponse
    {
        $categories = Category::query()->withCount(self::publishedCount())->orderBy('name')->get()
            ->map(fn (Category $c) => $this->present($c));

        return response()->json(['data' => $categories]);
    }

    /**
     * Get a category
     *
     * @unauthenticated
     */
    public function show(Category $category): JsonResponse
    {
        return response()->json(['data' => $this->present($category->loadCount(self::publishedCount()))]);
    }

    /**
     * Create a category
     *
     * `slug` is generated from `name` when left out. Categories are also created automatically when a movie is
     * posted with a new category name.
     */
    public function store(CategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return response()->json(['data' => $this->present($category->loadCount(self::publishedCount()))], 201);
    }

    /**
     * Update a category
     *
     * Change `name`, `slug` and/or `description`. Changing `slug` changes the category page URL.
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json(['data' => $this->present($category->loadCount(self::publishedCount()))]);
    }

    /**
     * Delete a category
     *
     * The category is removed from its movies; the movies themselves are kept.
     */
    public function destroy(Request $request, Category $category): Response
    {
        abort_unless($request->user()?->is_admin, 403);
        $category->delete();

        return response()->noContent();
    }

    /** movies_count counts only movies that are live on the site. */
    private static function publishedCount(): array
    {
        return ['movies' => fn ($q) => $q->published()];
    }

    /** @return array{id: int, name: string, slug: string, description: string|null, movies_count: int, url: string} */
    private function present(Category $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'description' => $c->description,
            'movies_count' => (int) $c->movies_count,
            'url' => route('movies.category', $c),
        ];
    }
}
