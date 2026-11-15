<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Stats;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Users & statistics', weight: 6)]
class StatsController extends Controller
{
    /**
     * Active users
     *
     * Daily active (last 30 days) and monthly active (last 12 months, distinct visitors) for logged-in users and
     * guests, plus sign-ups per day. Days are WIB. Guests are counted per browser; crawlers are not counted.
     */
    public function index(Request $request, Stats $stats): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        return response()->json([
            'totals' => $stats->totals(),
            'daily' => $stats->daily(30),
            'monthly' => $stats->monthly(12),
        ]);
    }
}
