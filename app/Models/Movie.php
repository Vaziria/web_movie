<?php

namespace App\Models;

use Database\Factories\MovieFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Movie extends Model
{
    /** @use HasFactory<MovieFactory> */
    use HasFactory;

    /** Sort keys accepted by the list endpoints => [column, direction]. */
    public const SORTS = [
        'created' => ['created_at', 'desc'],
        'rating' => ['rating_avg', 'desc'],
        'views' => ['views', 'desc'],
    ];

    public const MIN_SCORE = 1;

    public const MAX_SCORE = 5;

    /** Uploaded thumbnails live here (relative to public/). No symlinks: they are disabled on the shared host. */
    public const THUMBNAIL_DIR = 'uploads/thumbnails';

    protected $fillable = [
        'title', 'slug', 'synopsis', 'thumbnail_url', 'embed_url',
        'release_year', 'duration_minutes',
    ];

    protected $hidden = ['user_id'];

    protected function casts(): array
    {
        return [
            'views' => 'integer',
            'rating_avg' => 'float',
            'rating_count' => 'integer',
            'release_year' => 'integer',
            'duration_minutes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Movie $movie) {
            if (blank($movie->slug)) {
                $movie->slug = static::uniqueSlug($movie->title);
            }
        });

        static::saving(function (Movie $movie) {
            if ($movie->isDirty('embed_url')) {
                $movie->embed_url = static::normalizeEmbedUrl($movie->embed_url);
            }
        });

        static::updated(function (Movie $movie) {
            if ($movie->wasChanged('thumbnail_url')) {
                static::deleteLocalThumbnail($movie->getOriginal('thumbnail_url'));
            }
        });

        static::deleted(fn (Movie $movie) => static::deleteLocalThumbnail($movie->thumbnail_url));
    }

    /** Turn known "watch page" links into embeddable player URLs (see config/movies.php). */
    public static function normalizeEmbedUrl(string $url): string
    {
        foreach (config('movies.embed_rewrites', []) as $pattern => $replacement) {
            if (preg_match($pattern, $url)) {
                return preg_replace($pattern, $replacement, $url);
            }
        }

        return $url;
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'movie';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /** Remove an uploaded thumbnail file (stored as "/uploads/thumbnails/..."); remote URLs are ignored. */
    public static function deleteLocalThumbnail(?string $value): void
    {
        $prefix = '/'.self::THUMBNAIL_DIR.'/';
        if ($value && str_starts_with($value, $prefix)) {
            $path = public_path(ltrim($value, '/'));
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /** Absolute URL of the thumbnail (uploaded file or remote URL), or null. */
    public function thumbnailSrc(): ?string
    {
        $value = $this->thumbnail_url;
        if (blank($value)) {
            return null;
        }

        return str_starts_with($value, '/') ? url($value) : $value;
    }

    /** Plain-text summary for meta descriptions. */
    public function summary(int $limit = 155): string
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $this->synopsis));

        return $text !== '' ? Str::limit($text, $limit) : "Watch {$this->title} online on ".config('app.name').'.';
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->orderBy('name');
    }

    /** Apply search, category filter and sort. Unknown sort keys fall back to "created". */
    public function scopeBrowse(Builder $query, ?string $search, ?string $sort, ?string $category = null): Builder
    {
        if (filled($search)) {
            $term = '%'.addcslashes(trim($search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q
                ->where('title', 'like', $term)
                ->orWhere('synopsis', 'like', $term));
        }

        if (filled($category)) {
            $query->whereHas('categories', fn (Builder $q) => $q->where('slug', $category));
        }

        [$column, $direction] = self::SORTS[$sort] ?? self::SORTS['created'];

        return $query->orderBy($column, $direction)->orderByDesc('id');
    }

    public function recalculateRating(): void
    {
        $stats = $this->ratings()->selectRaw('COUNT(*) as c, AVG(score) as a')->first();
        $this->forceFill([
            'rating_count' => (int) $stats->c,
            'rating_avg' => round((float) $stats->a, 2),
        ])->saveQuietly();
    }
}
