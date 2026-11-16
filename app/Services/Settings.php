<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Admin-editable settings (Admin → Settings), cached. Keys in SECRET are encrypted with APP_KEY and never shown back
 * in the panel.
 */
class Settings
{
    public const SECRET = ['google_client_secret'];

    private const CACHE_KEY = 'settings:all';

    /** @var array<string, ?string>|null */
    private ?array $all = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $this->all ??= Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('settings')->pluck('value', 'key')->all());
        $value = $this->all[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        if (in_array($key, self::SECRET, true)) {
            try {
                return Crypt::decryptString($value);
            } catch (DecryptException) {
                return $default;   // APP_KEY changed: treat as unset
            }
        }

        return $value;
    }

    public function has(string $key): bool
    {
        return filled($this->get($key));
    }

    /** @param array<string, ?string> $values  null or '' clears a key */
    public function put(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value === null || $value === '') {
                DB::table('settings')->where('key', $key)->delete();

                continue;
            }
            $stored = in_array($key, self::SECRET, true) ? Crypt::encryptString((string) $value) : (string) $value;
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $stored, 'updated_at' => now(), 'created_at' => now()]);
        }
        Cache::forget(self::CACHE_KEY);
        $this->all = null;
    }

    // ---- typed accessors -------------------------------------------------------------

    public function googleClientId(): ?string
    {
        return $this->get('google_client_id', config('services.google.client_id'));
    }

    public function googleEnabled(): bool
    {
        return filled($this->googleClientId()) && $this->has('google_client_secret') && $this->get('google_enabled', '1') === '1';
    }

    /** Free movies for visitors who are not logged in; 0 = no limit. */
    public function guestFreeMovies(): int
    {
        return max(0, (int) $this->get('guest_free_movies', (string) config('movies.guest_free_movies')));
    }

    /** "total" (the free movies are used up for good) or "day" (they reset every day, WIB). */
    public function guestLimitPeriod(): string
    {
        $p = $this->get('guest_limit_period', config('movies.guest_limit_period'));

        return in_array($p, ['total', 'day'], true) ? $p : 'total';
    }

    public function premiumEnabled(): bool
    {
        return $this->get('premium_enabled', '0') === '1';
    }

    public function contactEmail(): ?string
    {
        return $this->get('contact_email');
    }
}
