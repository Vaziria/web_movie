<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::query()->withCount('movies')->orderBy('name')->get()
            ->map(fn (Category $c) => $this->present($c));

        return response()->json(['data' => $categories]);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json(['data' => $this->present($category->loadCount('movies'))]);
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return response()->json(['data' => $this->present($category->loadCount('movies'))], 201);
    }

    public function update(CategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json(['data' => $this->present($category->loadCount('movies'))]);
    }

    /** Deleting a category only detaches it from movies; the movies stay. */
    public function destroy(Request $request, Category $category): Response
    {
        abort_unless($request->user()?->is_admin, 403);
        $category->delete();

        return response()->noContent();
    }

    private function present(Category $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'description' => $c->description,
            'movies_count' => $c->movies_count,
            'url' => route('movies.category', $c),
        ];
    }
}
