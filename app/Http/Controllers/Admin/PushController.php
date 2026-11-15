<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PushMessageRequest;
use App\Models\PushMessage;
use App\Models\PushSubscription;
use App\Services\Push\PushDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PushController extends Controller
{
    public function index(): View
    {
        $lastRun = Cache::get(PushDispatcher::LAST_RUN_KEY);

        return view('admin.push.index', [
            'enabled' => PushDispatcher::enabled(),
            'total' => PushSubscription::count(),
            'byLocale' => PushSubscription::query()->selectRaw('locale, count(*) as n')->groupBy('locale')->pluck('n', 'locale'),
            'messages' => PushMessage::query()->with('movie')->latest('id')->limit(20)->get(),
            'lastRun' => $lastRun ? Carbon::parse($lastRun) : null,
        ]);
    }

    public function store(PushMessageRequest $request): RedirectResponse
    {
        abort_unless(PushDispatcher::enabled(), 409, 'Push notifications are not set up.');
        PushMessage::create($request->message());

        return redirect()->route('admin.push.index')->with('status', 'Queued. It goes out within a minute.');
    }
}
