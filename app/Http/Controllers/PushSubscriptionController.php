<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\Push\PushDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Browser subscribe/unsubscribe for new-movie notifications (called by resources/js/push.js). */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(PushDispatcher::enabled(), 404);

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth' => ['required', 'string', 'max:100'],
            'contentEncoding' => ['sometimes', 'in:aes128gcm,aesgcm'],
            'locale' => ['sometimes', 'in:'.implode(',', array_keys(site_locales()))],
        ]);
        if (! PushSubscription::endpointAllowed($data['endpoint'])) {
            return response()->json(['message' => 'Unsupported push service.'], 422);
        }

        $sub = PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashFor($data['endpoint'])],
            [
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'locale' => $data['locale'] ?? 'id',
            ],
        );
        $sub->forceFill(['failures' => 0])->save();

        return response()->json(['subscribed' => true], $sub->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);
        PushSubscription::query()->where('endpoint_hash', PushSubscription::hashFor($data['endpoint']))->delete();

        return response()->json(['subscribed' => false]);
    }
}
