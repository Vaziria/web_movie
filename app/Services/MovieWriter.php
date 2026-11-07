<?php

namespace App\Services;

use App\Http\Requests\MovieRequest;
use App\Models\Category;
use App\Models\Movie;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Creates and updates movies from a validated MovieRequest. Shared by the API and the admin panel. */
class MovieWriter
{
    public function save(MovieRequest $request, ?Movie $movie = null, ?User $author = null): Movie
    {
        return DB::transaction(function () use ($request, $movie, $author) {
            $data = $this->attributes($request, $movie);
            $movie ??= new Movie;
            $movie->fill($data);
            if (! $movie->exists && $author) {
                $movie->user()->associate($author);
            }
            $movie->save();

            if ($request->has('categories')) {
                $movie->categories()->sync(Category::idsForNames($request->validated('categories') ?? []));
            }
            if ($request->has('tags')) {
                $movie->tags()->sync(Tag::idsForNames($request->validated('tags') ?? []));
            }

            return $movie->refresh()->load('categories', 'tags');
        });
    }

    /** Validated movie columns, with an uploaded thumbnail file turned into its public path. */
    private function attributes(MovieRequest $request, ?Movie $movie): array
    {
        $data = collect($request->validated())->except(['thumbnail', 'categories', 'tags', 'remove_thumbnail', 'status', 'published_at'])->all();
        $data += $this->publishing($request, $movie);

        if ($request->file('thumbnail') instanceof UploadedFile) {
            $data['thumbnail_url'] = $this->storeThumbnail($request->file('thumbnail'));
        } elseif ($request->boolean('remove_thumbnail')) {
            $data['thumbnail_url'] = null;
        }

        return $data;
    }

    /**
     * published_at from `status` + `published_at`. status: draft = null, scheduled = the given (future) time,
     * published = the given time, the current one if already live, or now. No status: a given published_at is used
     * as is (null = draft); nothing given = unchanged (new movies default to "now").
     */
    private function publishing(MovieRequest $request, ?Movie $movie): array
    {
        $given = array_key_exists('published_at', $request->validated());
        $at = $given ? Movie::parsePublishedAt($request->validated('published_at')) : null;

        return match ($request->validated('status')) {
            'draft' => ['published_at' => null],
            'scheduled' => ['published_at' => $at],
            'published' => ['published_at' => $at ?? ($movie?->isPublished() ? $movie->published_at : now())],
            default => $given ? ['published_at' => $at] : [],
        };
    }

    private function storeThumbnail(UploadedFile $file): string
    {
        $dir = public_path(Movie::THUMBNAIL_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = Str::random(32).'.'.($file->guessExtension() ?: 'jpg');
        $file->move($dir, $name);

        return '/'.Movie::THUMBNAIL_DIR.'/'.$name;
    }
}
