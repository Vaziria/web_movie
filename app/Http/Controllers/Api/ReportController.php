<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\PlayerReport;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Admin: open broken-player reports, grouped per movie. */
#[Group('Reports', 'Visitor "Video not working?" reports. Reasons: `not_loading`, `wrong_video`, `bad_quality`, `other`.', weight: 4)]
class ReportController extends Controller
{
    /**
     * List open reports
     *
     * One entry per movie with open reports, most reported first. `servers` and `reasons` map a server number /
     * reason to its count.
     *
     * @response array{data: list<array{movie: array{slug: string, title: string}, open_reports: int, servers: array<string, int>, reasons: array<string, int>, latest_at: string|null}>}
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $data = PlayerReport::open()->with('movie:id,slug,title')->get()
            ->groupBy('movie_id')
            ->map(fn ($reports) => [
                'movie' => ['slug' => $reports->first()->movie->slug, 'title' => $reports->first()->movie->title],
                'open_reports' => $reports->count(),
                'servers' => $reports->countBy('server')->sortKeys(),
                'reasons' => $reports->countBy('reason'),
                'latest_at' => $reports->max('created_at')?->toIso8601String(),
            ])
            ->sortByDesc('open_reports')->values();

        return response()->json(['data' => $data]);
    }

    /**
     * Mark a movie's reports fixed
     *
     * Marks every open report of the movie as fixed and returns how many.
     *
     * @response array{resolved: int}
     */
    public function resolve(Request $request, Movie $movie): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $n = $movie->reports()->open()->update(['resolved_at' => now()]);

        return response()->json(['resolved' => $n]);
    }
}
