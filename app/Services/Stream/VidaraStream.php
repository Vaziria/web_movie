<?php

namespace App\Services\Stream;

use App\Models\Movie;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Direct HLS link for a vidara video, for the premium (ad-free) player and for Google (VideoObject contentUrl).
 *
 * Uses the call vidara's own player makes (POST https://vidara.to/api/stream). It is not part of vidara's documented
 * API and can change without notice; the owner owns the rights to his videos and chose this knowingly (2026-11-16).
 * The link expires after about 8 hours and is not tied to the IP that asked for it, so the server fetches it once and
 * caches it (1 hour by default, never past its expiry). Video bytes go from vidara's CDN straight to the viewer.
 */
class VidaraStream
{
    public const HOSTS = ['vidara.to', 'vidara.so', 'www.vidara.to', 'www.vidara.so'];

    public const LAST_RESULT_KEY = 'vidara:last';

    /** Vidara file code of the movie's first vidara player (Server 1 first), or null when it has none. */
    public static function filecode(Movie $movie): ?string
    {
        foreach ($movie->players() as $url) {
            if ($code = self::filecodeFromUrl((string) $url)) {
                return $code;
            }
        }

        return null;
    }

    public static function filecodeFromUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if (! in_array(strtolower($parts['host'] ?? ''), self::HOSTS, true)) {
            return null;
        }

        return preg_match('~^/(?:e|v)/([A-Za-z0-9]{5,40})/?$~', $parts['path'] ?? '', $m) ? $m[1] : null;
    }

    /** Cached direct link (master.m3u8) or null when vidara doesn't give one right now. */
    public function url(string $filecode): ?string
    {
        $key = 'vidara:stream:'.$filecode;
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached['url'] ?? null;   // ['url' => null] = a recent failure, don't ask again yet
        }

        [$url, $error] = $this->fetch($filecode);
        Cache::put(self::LAST_RESULT_KEY, ['ok' => $url !== null, 'at' => now()->getTimestamp(), 'error' => $error], now()->addDays(7));

        if ($url === null) {
            Log::warning('Vidara stream link failed', ['filecode' => $filecode, 'error' => $error]);
            Cache::put($key, ['url' => null], (int) config('movies.stream_retry_seconds'));

            return null;
        }

        Cache::put($key, ['url' => $url], $this->ttl($url));

        return $url;
    }

    /** @return array{0: ?string, 1: ?string} [url, error] */
    private function fetch(string $filecode): array
    {
        try {
            $res = Http::timeout(8)->connectTimeout(4)->acceptJson()
                ->withUserAgent('Mozilla/5.0 (compatible; MovieMov/1.0; +'.rtrim((string) config('app.url'), '/').')')
                ->withHeaders(['Referer' => 'https://vidara.to/e/'.$filecode])
                ->post((string) config('movies.vidara_stream_api'), ['filecode' => $filecode, 'device' => 'web']);
        } catch (Throwable $e) {
            return [null, 'request failed: '.class_basename($e)];
        }

        if (! $res->successful()) {
            return [null, 'HTTP '.$res->status()];
        }

        $url = $res->json('streaming_url');
        if (! is_string($url) || ! self::looksLikeStream($url)) {
            return [null, 'no usable streaming_url'];
        }

        return [$url, null];
    }

    /** Only https playlist URLs on a public host name are ever handed to browsers or Google. */
    public static function looksLikeStream(string $url): bool
    {
        $p = parse_url($url);
        $host = strtolower($p['host'] ?? '');

        return ($p['scheme'] ?? '') === 'https'
            && $host !== '' && $host !== 'localhost' && ! filter_var($host, FILTER_VALIDATE_IP)
            && str_ends_with(strtolower($p['path'] ?? ''), '.m3u8');
    }

    /** Cache time: the configured time, but always 10 minutes before the link's own expiry (token "<hex>-<unix>-..."). */
    private function ttl(string $url): int
    {
        $ttl = (int) config('movies.stream_cache_seconds');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $parts = explode('-', (string) ($q['token'] ?? ''));
        if (isset($parts[1]) && ctype_digit($parts[1])) {
            $ttl = min($ttl, (int) $parts[1] - now()->getTimestamp() - 600);
        }

        return max(30, $ttl);
    }
}
