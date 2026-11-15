<?php

namespace App\Services\Push;

use App\Models\PushSubscription;

/** Delivers encrypted payloads to push services. Swapped for a fake in tests. */
interface PushTransport
{
    /**
     * @param  iterable<int, array{0: PushSubscription, 1: string}>  $items  subscription + JSON payload
     * @return array<int, string> subscription id => 'ok' | 'expired' | 'failed'
     */
    public function send(iterable $items): array;
}
