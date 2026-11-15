<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;

/** Real delivery with minishlink/web-push (VAPID + payload encryption), one HTTP request per subscriber. */
class WebPushTransport implements PushTransport
{
    public function send(iterable $items): array
    {
        // A logger is required: without GMP/BCMath (our Docker image, possibly Hostinger) the library otherwise raises
        // an E_USER_NOTICE "install GMP or BCMath", which Laravel turns into an exception and nothing is sent.
        $push = new WebPush(['VAPID' => [
            'subject' => config('webpush.subject'),
            'publicKey' => config('webpush.public_key'),
            'privateKey' => config('webpush.private_key'),
        ]], ['TTL' => (int) config('webpush.ttl'), 'urgency' => 'normal'], logger: app(LoggerInterface::class));
        $push->setReuseVAPIDHeaders(true);

        $byEndpoint = [];
        foreach ($items as [$sub, $payload]) {
            /** @var PushSubscription $sub */
            $byEndpoint[$sub->endpoint] = $sub->id;
            $push->queueNotification(Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->public_key,
                'authToken' => $sub->auth_token,
                'contentEncoding' => $sub->content_encoding,
            ]), $payload);
        }

        $results = [];
        foreach ($push->flush() as $report) {
            $id = $byEndpoint[$report->getEndpoint()] ?? null;
            if ($id === null) {
                continue;
            }
            $results[$id] = match (true) {
                $report->isSuccess() => 'ok',
                $report->isSubscriptionExpired() => 'expired',
                default => 'failed',
            };
            if ($results[$id] === 'failed') {
                Log::warning('Push failed', ['subscription' => $id, 'reason' => $report->getReason()]);
            }
        }

        return $results;
    }
}
