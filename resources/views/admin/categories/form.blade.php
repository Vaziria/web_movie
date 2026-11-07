@extends('admin.layout')

@php
    $editing = $category->exists;
    $input = 'w-full rounded-lg border border-ink-700 bg-ink-900 px-3 text-base text-white focus:border-accent focus:outline-none';
    $label = 'mb-1 block text-sm text-neutral-400';
@endphp

@section('title', $editing ? 'Edit '.$category->name : 'New category')

@section('content')
    <h1 class="mb-4 text-xl font-semibold text-white">{{ $editing ? 'Edit category' : 'New category' }}</h1>

    <form method="post" action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="max-w-xl space-y-5">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div>
            <label for="name" class="{{ $label }}">Name *</label>
            <input id="name" name="name" value="{{ old('name', $category->name) }}" required maxlength="100" class="{{ $input }} h-11">
        </div>
        <div>
            <label for="slug" class="{{ $label }}">URL slug</label>
            <input id="slug" name="slug" value="{{ old('slug', $category->slug) }}" placeholder="auto from name" class="{{ $input }} h-11"
                   @if ($editing) required @endif>
            <p class="mt-1 text-xs text-neutral-500">Lowercase letters, numbers and dashes. Changing it changes the page URL.</p>
        </div>
        <div>
            <label for="description" class="{{ $label }}">Description</label>
            <textarea id="description" name="description" rows="3" maxlength="2000" class="{{ $input }} py-2">{{ old('description', $category->description) }}</textarea>
            <p class="mt-1 text-xs text-neutral-500">Shown on the category page and used as its Google description. 1-2 sentences.</p>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="h-11 rounded-lg bg-accent px-6 font-medium text-white hover:bg-accent-hover">{{ $editing ? 'Save' : 'Create' }}</button>
            <a href="{{ route('admin.categories.index') }}" class="inline-flex h-11 items-center rounded-lg border border-ink-700 px-5 text-sm hover:border-neutral-500">Cancel</a>
        </div>
    </form>

    @if ($editing)
        <form method="post" action="{{ route('admin.categories.destroy', $category) }}" class="mt-10 border-t border-ink-800 pt-6"
              onsubmit="return confirm('Delete this category? Movies are kept.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm text-red-400 hover:text-red-300">Delete category</button>
        </form>
    @endif
@endsection
