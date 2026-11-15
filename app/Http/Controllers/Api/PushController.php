<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PushMessageRequest;
use App\Models\PushMessage;
use App\Models\PushSubscription;
use App\Services\Push\PushDispatcher;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

#[Group('Push notifications', 'Browser notifications to visitors who allowed them. New movies are announced automatically when they go live (unless `notify_subscribers` is false); these endpoints send a custom message and show the numbers. Messages are sent by a job that runs every minute.', weight: 5)]
class PushController extends Controller
{
    /**
     * Push stats
     *
     * Subscriber count and the 20 latest messages.
     */
    public function index(): JsonResponse
    {
        abort_unless(request()->user()?->is_admin, 403);

        return response()->json([
            /** False until VAPID keys are set on the server (`php artisan app:push-keys`). */
            'enabled' => PushDispatcher::enabled(),
            'subscribers' => PushSubscription::count(),
            /** When the sender last ran (ISO 8601), null if never: check the server cron job. */
            'last_run_at' => Cache::get(PushDispatcher::LAST_RUN_KEY),
            'messages' => PushMessage::query()->with('movie')->latest('id')->limit(20)->get()->map(fn (PushMessage $m) => $this->present($m)),
        ]);
    }

    /**
     * Send a custom notification
     *
     * Queued and sent to every subscriber within a minute. `409` when push is not set up on the server.
     */
    public function store(PushMessageRequest $request): JsonResponse
    {
        abort_unless(PushDispatcher::enabled(), 409, 'Push notifications are not set up.');
        $message = PushMessage::create($request->message());

        return response()->json(['data' => $this->present($message->refresh())], 201);
    }

    /** @return array{id: int, kind: string, label: string, status: string, sent: int, failed: int, expired: int, created_at: ?string, sent_at: ?string} */
    private function present(PushMessage $m): array
    {
        return [
            'id' => $m->id,
            /** `movie` (one new movie), `movies` (several at once) or `custom`. */
            'kind' => $m->kind,
            'label' => $m->label(),
            /** `queued`, `sending` or `sent`. */
            'status' => $m->status,
            'sent' => $m->sent,
            'failed' => $m->failed,
            /** Subscriptions the browser cancelled (removed). */
            'expired' => $m->expired,
            'created_at' => $m->created_at?->toIso8601String(),
            'sent_at' => $m->sent_at?->toIso8601String(),
        ];
    }
}
