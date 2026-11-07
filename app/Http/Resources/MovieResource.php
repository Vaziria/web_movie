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
            'thumbnail_url' => $this->thumbnailSrc(),
            'embed_url' => $this->embed_url,
            'backup_embed_urls' => $this->backup_embed_urls ?? [],
            'release_year' => $this->release_year,
            'duration_minutes' => $this->duration_minutes,
            'categories' => $this->whenLoaded('categories', fn () => $this->categories
                ->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug])->values()),
            'views' => $this->views,
            'rating_avg' => $this->rating_avg,
            'rating_count' => $this->rating_count,
            'views_week' => $this->when(array_key_exists('week_views', $this->resource->getAttributes()), fn () => (int) $this->week_views),
            'status' => $this->status(),
            'published_at' => $this->published_at?->toIso8601String(),
            'url' => route('movies.show', $this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
