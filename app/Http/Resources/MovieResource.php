<?php

namespace App\Http\Resources;

use App\Models\Movie;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Movie */
class MovieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'synopsis' => $this->synopsis,
            /** Absolute thumbnail URL (uploaded file or remote image), or null. */
            'thumbnail_url' => $this->thumbnailSrc(),
            /** Player URL (Server 1), already extracted from iframe code. */
            'embed_url' => $this->embed_url,
            /**
             * Backup players (Server 2, 3, ...). `[]` when none.
             *
             * @var string[]
             */
            'backup_embed_urls' => $this->backup_embed_urls ?? [],
            'release_year' => $this->release_year,
            'duration_minutes' => $this->duration_minutes,
            'categories' => $this->whenLoaded('categories', fn () => $this->categories
                ->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug])->values()),
            /** Website page views, one per visitor session (API reads do not count). */
            'views' => $this->views,
            /** Average visitor rating 1-5 (two decimals); `0` when unrated. */
            'rating_avg' => $this->rating_avg,
            'rating_count' => $this->rating_count,
            /** Only with `sort=trending`: views in the last 7 days. */
            'views_week' => $this->when(array_key_exists('week_views', $this->resource->getAttributes()), fn () => (int) $this->week_views),
            /** `published` (live), `scheduled` (goes live at `published_at`) or `draft` (hidden). */
            'status' => $this->status(),
            /** Publish time in UTC; `null` for drafts. */
            'published_at' => $this->published_at?->toIso8601String(),
            /** Public page (Indonesian). English: the same path under `/en`. */
            'url' => route('movies.show', $this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
