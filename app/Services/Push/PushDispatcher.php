<?php

namespace App\Services\Push;

use App\Models\Movie;
use App\Models\PushMessage;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Runs every minute (`app:push-send`, Laravel scheduler):
 *  1. movies that just went live (published now or their scheduled time passed) become one message,
 *  2. queued messages are sent to every subscriber in batches, resuming where a crashed run stopped.
 */
class PushDispatcher
{
    public const LAST_RUN_KEY = 'push:last_run';

    public function __construct(private PushTransport $transport) {}

    public static function enabled(): bool
    {
        return filled(config('webpush.public_key')) && filled(config('webpush.private_key'));
    }

    /** @return array{queued: int, messages: int, sent: int, failed: int, expired: int} */
    public function run(): array
    {
        Cache::forever(self::LAST_RUN_KEY, now()->toIso8601String());
        $queued = $this->queueNewMovies();

        $totals = ['queued' => $queued, 'messages' => 0, 'sent' => 0, 'failed' => 0, 'expired' => 0];
        foreach (PushMessage::query()->pending()->get() as $message) {
            $stats = $this->deliver($message);
            $totals['messages']++;
            foreach (['sent', 'failed', 'expired'] as $k) {
                $totals[$k] += $stats[$k];
            }
        }

        return $totals;
    }

    /** Movies that went live and were not announced yet: one message for one movie, a summary for several. */
    public function queueNewMovies(): int
    {
        return DB::transaction(function () {
            $live = Movie::query()->published()->whereNull('push_notified_at')->lockForUpdate()->orderBy('published_at')->get();
            if ($live->isEmpty()) {
                return 0;
            }
            Movie::query()->whereKey($live->modelKeys())->update(['push_notified_at' => now()]);

            $cutoff = now()->subHours((int) config('webpush.announce_within_hours'));
            $announce = $live->filter(fn (Movie $m) => $m->push_notify && $m->published_at >= $cutoff)->values();
            if ($announce->isEmpty()) {
                return 0;
            }

            if ($announce->count() === 1) {
                PushMessage::create(['kind' => 'movie', 'movie_id' => $announce[0]->id]);
            } else {
                // Newest first in the summary text.
                PushMessage::create(['kind' => 'movies', 'movie_ids' => $announce->reverse()->pluck('id')->values()->all()]);
            }

            return 1;
        });
    }

    /** @return array{sent: int, failed: int, expired: int} */
    public function deliver(PushMessage $message): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'expired' => 0];
        // A resumed message (status "sending" after a crash) keeps its earlier counts and continues after `cursor`.
        $base = ['sent' => $message->sent, 'failed' => $message->failed, 'expired' => $message->expired];
        $message->forceFill(['status' => 'sending'])->save();
        $payloads = [];

        PushSubscription::query()->where('id', '>', $message->cursor)->orderBy('id')
            ->chunkById((int) config('webpush.batch'), function ($subs) use ($message, $base, &$stats, &$payloads) {
                $items = $subs->map(function (PushSubscription $s) use ($message, &$payloads) {
                    $locale = array_key_exists($s->locale, site_locales()) ? $s->locale : 'id';
                    $payloads[$locale] ??= json_encode($message->payloadFor($locale), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                    return [$s, $payloads[$locale]];
                })->all();

                $this->record($subs, $this->transport->send($items), $stats);

                $message->forceFill([
                    'cursor' => $subs->last()->id,
                    'sent' => $base['sent'] + $stats['sent'],
                    'failed' => $base['failed'] + $stats['failed'],
                    'expired' => $base['expired'] + $stats['expired'],
                ])->save();
            });

        $message->forceFill(['status' => 'sent', 'sent_at' => now()])->save();

        return $stats;
    }

    /** @param array<int, string> $results */
    private function record($subs, array $results, array &$stats): void
    {
        $max = (int) config('webpush.max_failures');
        foreach ($subs as $sub) {
            $result = $results[$sub->id] ?? 'failed';
            if ($result === 'ok') {
                $stats['sent']++;
                $sub->forceFill(['failures' => 0, 'last_sent_at' => now()])->save();
            } elseif ($result === 'expired') {
                $stats['expired']++;
                $sub->delete();
            } else {
                $stats['failed']++;
                $sub->failures + 1 >= $max ? $sub->delete() : $sub->increment('failures');
            }
        }
    }
}
