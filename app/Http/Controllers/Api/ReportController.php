<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\PlayerReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Admin: open broken-player reports, grouped per movie. */
class ReportController extends Controller
{
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

    public function resolve(Request $request, Movie $movie): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $n = $movie->reports()->open()->update(['resolved_at' => now()]);

        return response()->json(['resolved' => $n]);
    }
}
