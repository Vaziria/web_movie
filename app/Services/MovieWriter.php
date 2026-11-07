<?php

namespace App\Services;

use App\Http\Requests\MovieRequest;
use App\Models\Category;
use App\Models\Movie;
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
            $movie ??= new Movie;
            $movie->fill($this->attributes($request));
            if (! $movie->exists && $author) {
                $movie->user()->associate($author);
            }
            $movie->save();

            if ($request->has('categories')) {
                $movie->categories()->sync(Category::idsForNames($request->validated('categories') ?? []));
            }

            return $movie->refresh()->load('categories');
        });
    }

    /** Validated movie columns, with an uploaded thumbnail file turned into its public path. */
    private function attributes(MovieRequest $request): array
    {
        $data = collect($request->validated())->except(['thumbnail', 'categories', 'remove_thumbnail'])->all();

        if ($request->file('thumbnail') instanceof UploadedFile) {
            $data['thumbnail_url'] = $this->storeThumbnail($request->file('thumbnail'));
        } elseif ($request->boolean('remove_thumbnail')) {
            $data['thumbnail_url'] = null;
        }

        return $data;
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
