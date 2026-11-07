<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\PlayerReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReportController extends Controller
{
    /** Open broken-player reports, one row per movie, most reported first. */
    public function index(): View
    {
        $open = PlayerReport::open()->with('movie:id,slug,title,thumbnail_url')->latest()->get();

        $rows = $open->groupBy('movie_id')->map(fn ($reports) => [
            'movie' => $reports->first()->movie,
            'count' => $reports->count(),
            'servers' => $reports->countBy('server')->sortKeys(),
            'reasons' => $reports->countBy('reason')->sortDesc(),
            'latest' => $reports->max('created_at'),
        ])->sortByDesc('count')->values();

        return view('admin.reports.index', compact('rows'));
    }

    /** Mark every open report of a movie as fixed. */
    public function resolve(Movie $movie): RedirectResponse
    {
        $n = $movie->reports()->open()->update(['resolved_at' => now()]);

        return redirect()->route('admin.reports.index')->with('status', "Marked {$n} report(s) for \"{$movie->title}\" as fixed.");
    }
}
