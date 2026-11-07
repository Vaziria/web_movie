<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** A free keyword on movies (e.g. "Korea", "Tom Hanks", "zombie"). Pages at /tag/{slug}. */
class Tag extends Model
{
    /** Max tags per movie, and max tags combined in one list filter. */
    public const MAX_PER_MOVIE = 20;

    public const MAX_FILTER = 5;

    protected $fillable = ['name', 'slug'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function movies(): BelongsToMany
    {
        return $this->belongsToMany(Movie::class);
    }

    /** Tags with at least one published movie; adds `movies_count` (published only). */
    public function scopeInUse(Builder $query): Builder
    {
        return $query->whereHas('movies', fn ($m) => $m->published())
            ->withCount(['movies' => fn ($m) => $m->published()]);
    }

    public static function slugFor(string $name): string
    {
        return Str::slug($name) ?: Str::lower(trim($name));
    }

    /**
     * Resolve tag names to ids, creating missing ones. Matching is by slug, so "Tom Hanks" and "tom hanks" are
     * the same tag.
     *
     * @param  array<int, string>  $names
     * @return Collection<int, int>
     */
    public static function idsForNames(array $names): Collection
    {
        return collect($names)
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique(fn ($n) => static::slugFor($n))
            ->map(fn ($n) => static::firstOrCreate(['slug' => static::slugFor($n)], ['name' => $n])->id)
            ->values();
    }

    /**
     * Tag slugs from a list filter: "korea,zombie" or ["korea", "zombie"]. Trimmed, lowercased, unique, max
     * MAX_FILTER.
     *
     * @return array<int, string>
     */
    public static function parseFilter(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return collect($items)
            ->filter(fn ($s) => is_string($s))
            ->map(fn ($s) => Str::lower(trim($s)))
            ->filter()->unique()->take(self::MAX_FILTER)->values()->all();
    }
}
