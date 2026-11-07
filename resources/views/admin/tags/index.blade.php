@extends('admin.layout')

@section('title', 'Tags')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-white">Tags <span class="text-sm font-normal text-neutral-500">({{ $tags->count() }})</span></h1>
        <a href="{{ route('admin.tags.create') }}" class="inline-flex h-10 items-center rounded-lg bg-accent px-4 text-sm font-medium text-white hover:bg-accent-hover">+ New tag</a>
    </div>
    <p class="mb-4 text-sm text-neutral-500">Tags are created automatically when you type them in a movie's "Tags" box. Most used first.</p>

    @if ($tags->isEmpty())
        <p class="py-12 text-center text-neutral-500">No tags yet.</p>
    @else
        <ul class="divide-y divide-ink-800 border-y border-ink-800">
            @foreach ($tags as $t)
                <li class="flex items-center gap-3 py-3">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('admin.tags.edit', $t) }}" class="font-medium text-white hover:underline">#{{ $t->name }}</a>
                        <p class="line-clamp-1 text-xs text-neutral-500">/tag/{{ $t->slug }} · {{ $t->movies_count }} {{ \Illuminate\Support\Str::plural('movie', $t->movies_count) }}</p>
                    </div>
                    <a href="{{ route('admin.movies.index', ['tag' => $t->slug]) }}" class="shrink-0 text-sm text-neutral-400 hover:text-white">Movies</a>
                    <a href="{{ route('admin.tags.edit', $t) }}" class="shrink-0 text-sm text-neutral-400 hover:text-white">Edit</a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
