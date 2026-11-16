<?php

namespace App\Http\Controllers;

use App\Services\Settings;
use App\Services\Stream\Premium;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** /premium: what premium gives, the visitor's status, and how to get it (payments come later: contact for now). */
class PremiumController extends Controller
{
    public function show(Request $request, Premium $premium, Settings $settings): View
    {
        abort_unless($premium->enabled(), 404);

        return view('pages.premium', [
            'user' => $request->user(),
            'contact' => $settings->contactEmail(),
            'seo' => ['title' => __('Premium: watch without ads').' | '.config('app.name'), 'robots' => 'noindex, follow'],
        ]);
    }
}
