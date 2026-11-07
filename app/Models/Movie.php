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
        'duration' => ['duration_minutes', 'desc'],
    ];

    public const MIN_SCORE = 1;

    public const MAX_SCORE = 5;

    /** Backup players allowed in addition to embed_url (Server 1). */
    public const MAX_BACKUPS = 5;

    /** Uploaded thumbnails live here (relative to public/). No symlinks: they are disabled on the shared host. */
    public const THUMBNAIL_DIR = 'uploads/thumbnails';

    protected $fillable = [
        'title', 'slug', 'synopsis', 'thumbnail_url', 'embed_url', 'backup_embed_urls',
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
            'backup_embed_urls' => 'array',
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
            if ($movie->isDirty('backup_embed_urls')) {
                $backups = collect($movie->backup_embed_urls ?? [])
                    ->map(fn ($u) => static::normalizeEmbedUrl(trim((string) $u)))
                    ->filter()->unique()->reject(fn ($u) => $u === $movie->embed_url)->values()->all();
                $movie->backup_embed_urls = $backups ?: null;
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

    /** Pull the src out of a pasted `<iframe src="...">` embed code; other input is returned trimmed. */
    public static function extractEmbedSrc(string $value): string
    {
        if (preg_match('~<iframe\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']~i', $value, $m)) {
            return html_entity_decode(trim($m[1]));
        }

        return trim($value);
    }

    /** All players, Server 1 first: [1 => url, 2 => url, ...]. */
    public function players(): array
    {
        $all = array_values(array_filter([$this->embed_url, ...($this->backup_embed_urls ?? [])]));

        return array_combine(range(1, max(1, count($all))), $all ?: ['']);
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

        return $text !== '' ? Str::limit($text, $limit) : __('Watch :title online on :site.', ['title' => $this->title, 'site' => config('app.name')]);
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

    public function reports(): HasMany
    {
        return $this->hasMany(PlayerReport::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->orderBy('name');
    }

    /**
     * Apply search, category filter and sort. Unknown sort keys fall back to "created"; $direction ("asc"/"desc")
     * overrides the sort's default. Movies without a value for the sort column (e.g. no duration) always go last.
     */
    public function scopeBrowse(Builder $query, ?string $search, ?string $sort, ?string $category = null, ?string $direction = null): Builder
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

        [$column, $default] = self::SORTS[$sort] ?? self::SORTS['created'];
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : $default;

        return $query
            ->orderByRaw($query->getGrammar()->wrap($column).' IS NULL')
            ->orderBy($column, $direction)
            ->orderBy('id', $direction);
    }

    /** "1h 45m" / "1j 45m" (short, cards) or "1 hour 45 minutes" / "1 jam 45 menit" (long). */
    public function durationLabel(bool $long = false): ?string
    {
        $m = $this->duration_minutes;
        if (! $m) {
            return null;
        }
        [$h, $min] = [intdiv($m, 60), $m % 60];

        if ($long) {
            $parts = array_filter([
                $h ? trans_choice(':count hour|:count hours', $h, ['count' => $h]) : null,
                $min ? trans_choice(':count minute|:count minutes', $min, ['count' => $min]) : null,
            ]);

            return implode(' ', $parts);
        }

        return trim(($h ? $h.__('h').' ' : '').($min ? $min.__('m') : ''));
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
