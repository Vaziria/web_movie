@extends('admin.layout')

@section('title', 'Categories')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-white">Categories <span class="text-sm font-normal text-neutral-500">({{ $categories->count() }})</span></h1>
        <a href="{{ route('admin.categories.create') }}" class="inline-flex h-10 items-center rounded-lg bg-accent px-4 text-sm font-medium text-white hover:bg-accent-hover">+ New category</a>
    </div>

    @if ($categories->isEmpty())
        <p class="py-12 text-center text-neutral-500">No categories yet.</p>
    @else
        <ul class="divide-y divide-ink-800 border-y border-ink-800">
            @foreach ($categories as $c)
                <li class="flex items-center gap-3 py-3">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('admin.categories.edit', $c) }}" class="font-medium text-white hover:underline">{{ $c->name }}</a>
                        <p class="line-clamp-1 text-xs text-neutral-500">/category/{{ $c->slug }} · {{ $c->movies_count }} {{ \Illuminate\Support\Str::plural('movie', $c->movies_count) }}{{ $c->description ? ' · '.$c->description : ' · no description' }}</p>
                    </div>
                    <a href="{{ route('admin.categories.edit', $c) }}" class="shrink-0 text-sm text-neutral-400 hover:text-white">Edit</a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
