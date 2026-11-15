<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A movie a logged-in user opened (first and last time). */
class MovieWatch extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['user_id', 'movie_id', 'first_at', 'last_at'];

    protected function casts(): array
    {
        return ['first_at' => 'datetime', 'last_at' => 'datetime'];
    }

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }
}
