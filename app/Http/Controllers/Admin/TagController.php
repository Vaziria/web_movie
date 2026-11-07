<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TagController extends Controller
{
    public function index(): View
    {
        return view('admin.tags.index', ['tags' => Tag::withCount('movies')->orderByDesc('movies_count')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.tags.form', ['tag' => new Tag]);
    }

    public function store(TagRequest $request): RedirectResponse
    {
        $tag = Tag::create($request->validated());

        return redirect()->route('admin.tags.index')->with('status', "Created \"#{$tag->name}\".");
    }

    public function edit(Tag $tag): View
    {
        return view('admin.tags.form', compact('tag'));
    }

    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        $tag->update($request->validated());

        return redirect()->route('admin.tags.index')->with('status', "Saved \"#{$tag->name}\".");
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $name = $tag->name;
        $tag->delete();

        return redirect()->route('admin.tags.index')->with('status', "Deleted \"#{$name}\".");
    }
}
