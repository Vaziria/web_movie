<?php

namespace App\Console\Commands;

use App\Services\Push\PushDispatcher;
use Illuminate\Console\Command;

class SendPush extends Command
{
    protected $signature = 'app:push-send';

    protected $description = 'Announce movies that just went live and send queued push notifications (scheduled every minute)';

    public function handle(PushDispatcher $dispatcher): int
    {
        if (! PushDispatcher::enabled()) {
            $this->warn('Push is off: run `php artisan app:push-keys` first.');

            return self::SUCCESS;
        }

        $r = $dispatcher->run();
        $this->info("queued {$r['queued']}, messages {$r['messages']}, sent {$r['sent']}, failed {$r['failed']}, expired {$r['expired']}");

        return self::SUCCESS;
    }
}
