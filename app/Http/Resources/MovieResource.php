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
            'poster_url' => $this->poster_url,
            'embed_url' => $this->embed_url,
            'release_year' => $this->release_year,
            'duration_minutes' => $this->duration_minutes,
            'views' => $this->views,
            'rating_avg' => $this->rating_avg,
            'rating_count' => $this->rating_count,
            'url' => route('movies.show', $this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
