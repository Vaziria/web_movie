<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function movies(): BelongsToMany
    {
        return $this->belongsToMany(Movie::class);
    }

    /**
     * Resolve category names to ids, creating missing ones. Matching is by slug,
     * so "Sci-Fi", "sci fi" and "SCI-FI" are the same category.
     *
     * @param  array<int, string>  $names
     * @return Collection<int, int>
     */
    public static function idsForNames(array $names): Collection
    {
        return collect($names)
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique(fn ($n) => Str::slug($n))
            ->map(fn ($n) => static::firstOrCreate(['slug' => Str::slug($n) ?: Str::lower($n)], ['name' => $n])->id)
            ->values();
    }
}
