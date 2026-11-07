<?php

namespace App\Services;

use App\Models\Movie;
use App\Models\Rating;
use Illuminate\Support\Facades\DB;

/**
 * Anonymous ratings: one rating per browser (voter cookie) and per IP.
 * A repeat vote from the same browser or IP updates the existing rating instead of adding one.
 */
class RatingService
{
    public function rate(Movie $movie, string $voterToken, string $ip, int $score): Rating
    {
        $voterId = hash('sha256', $voterToken);
        $ipHash = hash_hmac('sha256', $ip, (string) config('app.key'));

        return DB::transaction(function () use ($movie, $voterId, $ipHash, $score) {
            $rating = Rating::where('movie_id', $movie->id)
                ->where(fn ($q) => $q->where('voter_id', $voterId)->orWhere('ip_hash', $ipHash))
                ->lockForUpdate()
                ->first();

            if ($rating) {
                $rating->update(['score' => $score, 'ip_hash' => $ipHash]);
            } else {
                $rating = Rating::create([
                    'movie_id' => $movie->id,
                    'voter_id' => $voterId,
                    'ip_hash' => $ipHash,
                    'score' => $score,
                ]);
            }

            $movie->recalculateRating();

            return $rating;
        });
    }

    public function scoreFor(Movie $movie, ?string $voterToken): ?int
    {
        if (blank($voterToken)) {
            return null;
        }

        return Rating::where('movie_id', $movie->id)
            ->where('voter_id', hash('sha256', $voterToken))
            ->value('score');
    }
}
