@extends('admin.layout')

@php
    $editing = $movie->exists;
    $input = 'w-full rounded-lg border border-ink-700 bg-ink-900 px-3 text-base text-white focus:border-accent focus:outline-none';
    $label = 'mb-1 block text-sm text-neutral-400';
    $remoteThumb = $movie->thumbnail_url && ! str_starts_with($movie->thumbnail_url, '/') ? $movie->thumbnail_url : '';
@endphp

@section('title', $editing ? 'Edit '.$movie->title : 'New movie')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="line-clamp-1 text-xl font-semibold text-white">{{ $editing ? 'Edit movie' : 'New movie' }}</h1>
        @if ($editing)
            <a href="{{ route('movies.show', $movie) }}" target="_blank" rel="noopener" class="shrink-0 text-sm text-neutral-400 hover:text-white">View page ↗</a>
        @endif
    </div>

    <form method="post" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.movies.update', $movie) : route('admin.movies.store') }}" class="space-y-5">
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="_admin_form" value="1">

        <div>
            <label for="title" class="{{ $label }}">Title *</label>
            <input id="title" name="title" value="{{ old('title', $movie->title) }}" required maxlength="255" class="{{ $input }} h-11">
        </div>

        <div>
            <label for="embed_url" class="{{ $label }}">Embed code or player URL *</label>
            <textarea id="embed_url" name="embed_url" rows="3" required class="{{ $input }} py-2 font-mono text-sm"
                      placeholder='<iframe src="https://vidara.to/e/XXXX" ...></iframe>'>{{ old('embed_url', $movie->embed_url) }}</textarea>
            <p class="mt-1 text-xs text-neutral-500">Paste the full &lt;iframe&gt; code or an https URL. vidara /v/ links are converted to /e/.</p>
        </div>

        <div>
            <label for="backup_embed_urls" class="{{ $label }}">Backup players (optional, max {{ \App\Models\Movie::MAX_BACKUPS }})</label>
            <textarea id="backup_embed_urls" name="backup_embed_urls" rows="3" class="{{ $input }} py-2 font-mono text-sm"
                      placeholder="One per line: iframe code or https URL">{{ implode("\n", (array) old('backup_embed_urls', $movie->backup_embed_urls ?? [])) }}</textarea>
            <p class="mt-1 text-xs text-neutral-500">Shown to visitors as Server 2, Server 3, … when Server 1 does not work.</p>
        </div>

        <fieldset class="rounded-lg border border-ink-800 p-4">
            <legend class="px-1 text-sm text-neutral-400">Thumbnail (16:9)</legend>
            @if ($src = $movie->thumbnailSrc())
                <img src="{{ $src }}" alt="Current thumbnail" class="mb-3 aspect-video w-full max-w-xs rounded object-cover" referrerpolicy="no-referrer">
                <label class="mb-3 flex items-center gap-2 text-sm text-neutral-400">
                    <input type="checkbox" name="remove_thumbnail" value="1" class="size-4"> Remove current thumbnail
                </label>
            @endif
            <label for="thumbnail" class="{{ $label }}">Upload image (jpg, png, webp, max 2 MB)</label>
            <input id="thumbnail" type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp"
                   class="block w-full text-sm text-neutral-400 file:mr-3 file:h-10 file:rounded-lg file:border-0 file:bg-ink-800 file:px-4 file:text-neutral-200">
            <label for="thumbnail_url" class="{{ $label }} mt-3">or image URL</label>
            <input id="thumbnail_url" name="thumbnail_url" type="url" value="{{ old('thumbnail_url', $remoteThumb) }}" placeholder="https://..." class="{{ $input }} h-11">
        </fieldset>

        <div>
            <label for="categories" class="{{ $label }}">Categories</label>
            <input id="categories" name="categories" value="{{ old('categories', $movie->categories?->pluck('name')->join(', ')) }}"
                   placeholder="Action, Drama" class="{{ $input }} h-11">
            <p class="mt-1 text-xs text-neutral-500">
                Comma separated. New names are created.
                @if ($allCategories->isNotEmpty()) Existing: {{ $allCategories->join(', ') }}. @endif
            </p>
        </div>

        <div>
            <label for="synopsis" class="{{ $label }}">Synopsis</label>
            <textarea id="synopsis" name="synopsis" rows="5" maxlength="10000" class="{{ $input }} py-2">{{ old('synopsis', $movie->synopsis) }}</textarea>
            <p class="mt-1 text-xs text-neutral-500">The first ~155 characters become the Google description.</p>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div>
                <label for="release_year" class="{{ $label }}">Year</label>
                <input id="release_year" name="release_year" type="number" min="1870" max="2100" inputmode="numeric" value="{{ old('release_year', $movie->release_year) }}" class="{{ $input }} h-11">
            </div>
            <div>
                <label for="duration_minutes" class="{{ $label }}">Minutes</label>
                <input id="duration_minutes" name="duration_minutes" type="number" min="1" max="1440" inputmode="numeric" value="{{ old('duration_minutes', $movie->duration_minutes) }}" class="{{ $input }} h-11">
            </div>
            <div class="col-span-2 sm:col-span-1">
                <label for="slug" class="{{ $label }}">URL slug</label>
                <input id="slug" name="slug" value="{{ old('slug', $movie->slug) }}" placeholder="auto from title" class="{{ $input }} h-11">
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="h-11 rounded-lg bg-accent px-6 font-medium text-white hover:bg-accent-hover">{{ $editing ? 'Save' : 'Create' }}</button>
            <a href="{{ route('admin.movies.index') }}" class="inline-flex h-11 items-center rounded-lg border border-ink-700 px-5 text-sm hover:border-neutral-500">Cancel</a>
        </div>
    </form>

    @if ($editing)
        <form method="post" action="{{ route('admin.movies.destroy', $movie) }}" class="mt-10 border-t border-ink-800 pt-6"
              onsubmit="return confirm('Delete this movie? This cannot be undone.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm text-red-400 hover:text-red-300">Delete movie</button>
        </form>
    @endif
@endsection
