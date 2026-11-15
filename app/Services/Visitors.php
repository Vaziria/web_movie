<?php

namespace App\Services;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

/**
 * Who may play a movie, and who was active when.
 *
 * Guests: the free movies they opened live in an (encrypted) cookie, so re-opening one of them never counts again.
 * Clearing cookies resets it; counting by IP is not used on purpose (Indonesian mobile networks share IPs).
 * Crawlers never send the cookie, so they always see the player (no SEO impact).
 */
class Visitors
{
    public const SEEN_COOKIE = 'mm_free';

    public const VISITOR_COOKIE = 'mm_vid';

    public function __construct(private Settings $settings) {}

    /**
     * May this request play the movie? Records the movie as one of the guest's free ones when there is room.
     *
     * @return array{allowed: bool, guest: bool, limit: int, left: int, period: string}
     */
    public function access(Request $request, Movie $movie): array
    {
        $limit = $this->settings->guestFreeMovies();
        $period = $this->settings->guestLimitPeriod();
        if ($request->user() || $limit === 0) {
            return ['allowed' => true, 'guest' => ! $request->user(), 'limit' => $limit, 'left' => PHP_INT_MAX, 'period' => $period];
        }

        $seen = $this->seen($request, $period);
        $allowed = in_array($movie->id, $seen, true) || count($seen) < $limit;
        if ($allowed && ! in_array($movie->id, $seen, true)) {
            $seen[] = $movie->id;
            Cookie::queue(self::SEEN_COOKIE, json_encode(['d' => $this->today(), 'ids' => $seen]), 60 * 24 * 400);
        }

        return ['allowed' => $allowed, 'guest' => true, 'limit' => $limit, 'left' => max(0, $limit - count($seen)), 'period' => $period];
    }

    /** @return array<int, int> */
    private function seen(Request $request, string $period): array
    {
        $data = json_decode((string) $request->cookie(self::SEEN_COOKIE), true);
        if (! is_array($data) || ! isset($data['ids']) || ! is_array($data['ids'])) {
            return [];
        }
        if ($period === 'day' && ($data['d'] ?? null) !== $this->today()) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $data['ids'])));
    }

    private function today(): string
    {
        return now()->timezone(config('movies.admin_timezone'))->toDateString();
    }

    /** Remember which movies a logged-in user opened. */
    public function recordWatch(User $user, Movie $movie): void
    {
        $now = now();
        DB::table('movie_watches')->upsert(
            [['user_id' => $user->id, 'movie_id' => $movie->id, 'first_at' => $now, 'last_at' => $now]],
            ['user_id', 'movie_id'],
            ['last_at'],
        );
    }

    /**
     * Daily active visitors (Admin → Statistics). Once per visitor per day: users by id, guests by a random id in a
     * long-lived cookie, counted only once the browser sends that cookie back (cookie-less crawlers never count).
     */
    public function recordActive(Request $request): void
    {
        $day = $this->today();
        $session = $request->hasSession() ? $request->session() : null;
        if ($session?->get('active_day') === $day) {
            return;
        }

        if ($user = $request->user()) {
            $visitor = 'u:'.$user->id;
            DB::table('users')->where('id', $user->id)->update(['last_active_at' => now()]);
        } else {
            if (self::looksLikeBot((string) $request->userAgent())) {
                return;
            }
            $vid = (string) $request->cookie(self::VISITOR_COOKIE);
            if (! preg_match('/^[a-z0-9]{20,32}$/', $vid)) {
                Cookie::queue(self::VISITOR_COOKIE, bin2hex(random_bytes(12)), 60 * 24 * 400);

                return;   // counted from the next request, when the cookie comes back
            }
            $visitor = 'g:'.$vid;
        }

        DB::table('daily_actives')->insertOrIgnore(['day' => $day, 'visitor' => $visitor, 'is_user' => $user !== null]);
        $session?->put('active_day', $day);
    }

    public static function looksLikeBot(string $userAgent): bool
    {
        return $userAgent === '' || (bool) preg_match('/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|telegram|preview|headless|lighthouse|curl|wget|python|okhttp|java\//i', $userAgent);
    }
}
