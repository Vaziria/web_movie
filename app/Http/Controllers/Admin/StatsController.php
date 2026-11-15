<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Stats;
use Illuminate\View\View;

class StatsController extends Controller
{
    public function index(Stats $stats): View
    {
        return view('admin.stats', ['daily' => $stats->daily(30), 'monthly' => $stats->monthly(12), 'totals' => $stats->totals()]);
    }
}
