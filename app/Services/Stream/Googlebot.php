<?php

namespace App\Services\Stream;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Is this request really Google's crawler? Google's documented check: the user agent says Googlebot (or the URL
 * Inspection / other Google fetchers), the IP's reverse DNS ends in googlebot.com or google.com, and that host name
 * resolves back to the same IP. A user agent alone proves nothing. Results are cached per IP for a day.
 */
class Googlebot
{
    public const USER_AGENTS = '/Googlebot|Google-InspectionTool|GoogleOther|Storebot-Google/i';

    public const DOMAINS = ['.googlebot.com', '.google.com'];

    /** @var callable(string): (string|false) */
    private $reverse;

    /** @var callable(string): array<int, string> */
    private $forward;

    public function __construct(?callable $reverse = null, ?callable $forward = null)
    {
        $this->reverse = $reverse ?? fn (string $ip) => @gethostbyaddr($ip);
        $this->forward = $forward ?? function (string $host): array {
            $ips = [];
            foreach ([DNS_A => 'ip', DNS_AAAA => 'ipv6'] as $type => $field) {
                foreach (@dns_get_record($host, $type) ?: [] as $r) {
                    if (isset($r[$field])) {
                        $ips[] = $r[$field];
                    }
                }
            }

            return $ips;
        };
    }

    public function is(Request $request): bool
    {
        $ip = (string) $request->ip();
        if ($ip === '' || ! preg_match(self::USER_AGENTS, (string) $request->userAgent())) {
            return false;
        }

        return Cache::remember('googlebot:'.$ip, now()->addDay(), fn () => $this->verify($ip));
    }

    public function verify(string $ip): bool
    {
        $host = strtolower((string) ($this->reverse)($ip));
        if ($host === '' || $host === $ip) {
            return false;
        }

        $inGoogle = false;
        foreach (self::DOMAINS as $suffix) {
            $inGoogle = $inGoogle || str_ends_with($host, $suffix);
        }
        if (! $inGoogle) {
            return false;
        }

        $normalize = fn (string $a) => inet_pton($a) ?: $a;   // compare IPv6 forms safely

        return in_array($normalize($ip), array_map($normalize, ($this->forward)($host)), true);
    }
}
