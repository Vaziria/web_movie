<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use App\Services\Stream\Premium;
use App\Services\Stream\VidaraStream;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * GET /stream/{slug}.m3u8 — a fixed address for the movie's video (premium player + Google's VideoObject
 * contentUrl). It forwards to the current vidara link (cached), so the address never changes while the link does.
 * Only premium members, admins and verified Googlebot get through.
 */
class StreamController extends Controller
{
    public function show(Request $request, Movie $movie, Premium $premium, VidaraStream $vidara): RedirectResponse|Response
    {
        abort_unless($movie->isPublished() && $premium->availableFor($movie), 404);

        if (! $premium->canWatch($request)) {
            return response('Premium only.', 403, ['Content-Type' => 'text/plain', 'Cache-Control' => 'private, no-store']);
        }

        $url = $vidara->url(VidaraStream::filecode($movie));
        if ($url === null) {
            return response('Stream temporarily unavailable.', 503, ['Content-Type' => 'text/plain', 'Retry-After' => '60', 'Cache-Control' => 'no-store']);
        }

        return redirect()->away($url, 302)->header('Cache-Control', 'private, no-store');
    }
}
