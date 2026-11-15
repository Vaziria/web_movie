<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** One notification for every subscriber: a new movie, several new movies, or a custom message. */
class PushMessage extends Model
{
    protected $fillable = ['kind', 'movie_id', 'movie_ids', 'title', 'body', 'url', 'status'];

    protected function casts(): array
    {
        return ['movie_ids' => 'array', 'sent_at' => 'datetime'];
    }

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['queued', 'sending'])->orderBy('id');
    }

    /** @return Collection<int, Movie> */
    public function movies(): Collection
    {
        $ids = (array) $this->movie_ids;

        return Movie::query()->whereIn('id', $ids)->get()->sortBy(fn (Movie $m) => array_search($m->id, $ids))->values();
    }

    /**
     * What the service worker shows: title, body, url, image, tag. Texts and links in the subscriber's language.
     *
     * @return array{title: string, body: string, url: string, image: ?string, icon: string, badge: string, tag: string}
     */
    public function payloadFor(string $locale): array
    {
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            $site = config('app.name');
            [$title, $body, $url, $image] = match ($this->kind) {
                'movie' => $this->movie
                    ? [__('New movie: :title', ['title' => $this->movie->title]), $this->movie->summary(120), lroute('movies.show', $this->movie, $locale), $this->movie->thumbnailSrc()]
                    : [$site, '', lroute('movies.index', [], $locale), null],
                'movies' => (function () use ($locale, $site) {
                    $movies = $this->movies();

                    return [
                        __(':count new movies on :site', ['count' => max(count((array) $this->movie_ids), 1), 'site' => $site]),
                        Str::limit($movies->pluck('title')->join(', '), 120),
                        lroute('movies.index', [], $locale),
                        $movies->first()?->thumbnailSrc(),
                    ];
                })(),
                default => [(string) $this->title, (string) $this->body, url($this->url ?: '/'), null],
            };
        } finally {
            app()->setLocale($previous);
        }

        return [
            'title' => Str::limit($title, 100),
            'body' => $body,
            'url' => $url,
            'image' => $image,
            'icon' => asset('icons/icon-192.png'),
            'badge' => asset('icons/badge-96.png'),
            'tag' => 'moviemov-'.$this->id,
        ];
    }

    /** Short English label for the admin list. */
    public function label(): string
    {
        return match ($this->kind) {
            'movie' => 'New movie: '.($this->movie?->title ?? '(deleted)'),
            'movies' => count((array) $this->movie_ids).' new movies: '.$this->movies()->pluck('title')->join(', '),
            default => (string) $this->title,
        };
    }
}
