<?php

namespace App\Models;

use Database\Factories\MovieFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    protected $fillable = [
        'title', 'slug', 'synopsis', 'poster_url', 'embed_url',
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

    /** Apply ?q= search and ?sort= ordering. Unknown sort keys fall back to "created". */
    public function scopeBrowse(Builder $query, ?string $search, ?string $sort): Builder
    {
        if (filled($search)) {
            $term = '%'.addcslashes(trim($search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q
                ->where('title', 'like', $term)
                ->orWhere('synopsis', 'like', $term));
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
