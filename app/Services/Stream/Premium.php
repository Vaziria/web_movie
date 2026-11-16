<?php

namespace App\Services\Stream;

use App\Models\Movie;
use App\Services\Settings;
use Illuminate\Http\Request;

/**
 * Premium (ad-free) player rules. Shown when the admin turned it on and the movie has a vidara player. Unlocked for
 * premium members and admins, and for verified Googlebot (a paywall: the page marks the player box as not free, so
 * Google may see it — see VideoObject `hasPart` in MovieController). Everyone else sees a locked box with the offer.
 */
class Premium
{
    public function __construct(private Settings $settings, private Googlebot $googlebot) {}

    public function enabled(): bool
    {
        return $this->settings->get('premium_enabled', '0') === '1';
    }

    public function availableFor(Movie $movie): bool
    {
        return $this->enabled() && VidaraStream::filecode($movie) !== null;
    }

    /** Paying member or admin (never Googlebot). */
    public function isMember(Request $request): bool
    {
        return (bool) $request->user()?->isPremium();
    }

    public function canWatch(Request $request): bool
    {
        return $this->isMember($request) || $this->googlebot->is($request);
    }
}
