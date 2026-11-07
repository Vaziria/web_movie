<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerReport extends Model
{
    public const REASONS = ['not_loading', 'wrong_video', 'bad_quality', 'other'];

    protected $fillable = ['movie_id', 'server', 'reason', 'ip_hash', 'resolved_at'];

    protected function casts(): array
    {
        return ['server' => 'integer', 'resolved_at' => 'datetime'];
    }

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    /** Number of movies that currently have at least one open report (admin nav badge). */
    public static function openMovieCount(): int
    {
        return (int) static::open()->distinct()->count('movie_id');
    }
}
