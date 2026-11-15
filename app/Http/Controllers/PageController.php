<?php

namespace App\Http\Controllers;

use App\Services\Settings;
use Illuminate\View\View;

class PageController extends Controller
{
    public function privacy(Settings $settings): View
    {
        $l = app()->getLocale();

        return view('pages.privacy', [
            'contact' => $settings->contactEmail(),
            'updated' => '2026-11-15',
            'seo' => [
                'title' => __('Privacy Policy').' | '.config('app.name'),
                'description' => __('What :site collects, why, and how you can delete your data.', ['site' => config('app.name')]),
                'canonical' => lroute('pages.privacy', [], $l),
                'alternates' => collect(array_keys(site_locales()))->mapWithKeys(fn ($x) => [$x => lroute('pages.privacy', [], $x)])
                    ->put('x-default', lroute('pages.privacy', [], 'id'))->all(),
            ],
        ]);
    }
}
