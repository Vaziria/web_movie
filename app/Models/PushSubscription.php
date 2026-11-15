<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A browser that allowed MovieMov notifications. */
class PushSubscription extends Model
{
    protected $fillable = ['endpoint_hash', 'endpoint', 'public_key', 'auth_token', 'content_encoding', 'locale'];

    protected function casts(): array
    {
        return ['last_sent_at' => 'datetime'];
    }

    public static function hashFor(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    /** Push services we accept (config webpush.allowed_hosts and their subdomains), https only. */
    public static function endpointAllowed(string $endpoint): bool
    {
        $parts = parse_url($endpoint);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['port'])) {
            return false;
        }
        $host = strtolower($parts['host']);

        foreach ((array) config('webpush.allowed_hosts') as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }
}
