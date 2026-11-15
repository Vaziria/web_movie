<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\PushMessage;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Push\PushDispatcher;
use App\Services\Push\PushTransport;
use App\Services\Push\WebPushTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Minishlink\WebPush\VAPID;
use Tests\TestCase;

/** Records what would be sent; results per endpoint can be forced ('ok' | 'expired' | 'failed'). */
class FakePushTransport implements PushTransport
{
    /** @var array<int, array{endpoint: string, payload: array}> */
    public array $sent = [];

    /** @var array<string, string> endpoint => result */
    public array $results = [];

    public function send(iterable $items): array
    {
        $out = [];
        foreach ($items as [$sub, $payload]) {
            $this->sent[] = ['endpoint' => $sub->endpoint, 'payload' => json_decode($payload, true)];
            $out[$sub->id] = $this->results[$sub->endpoint] ?? 'ok';
        }

        return $out;
    }
}

class PushTest extends TestCase
{
    use RefreshDatabase;

    private FakePushTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        config(['webpush.public_key' => 'BPublicKeyForTests', 'webpush.private_key' => 'privateKeyForTests']);
        $this->transport = new FakePushTransport;
        $this->app->instance(PushTransport::class, $this->transport);
    }

    private function subscribe(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc', string $locale = 'id'): PushSubscription
    {
        return PushSubscription::create([
            'endpoint_hash' => PushSubscription::hashFor($endpoint), 'endpoint' => $endpoint,
            'public_key' => 'BKey', 'auth_token' => 'auth', 'locale' => $locale,
        ]);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();

        return $u;
    }

    // ---- subscribing ---------------------------------------------------------------

    public function test_browser_can_subscribe_update_and_unsubscribe(): void
    {
        $body = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/xyz', 'keys' => ['p256dh' => 'BKey1', 'auth' => 'a1'], 'contentEncoding' => 'aes128gcm', 'locale' => 'en'];

        $this->postJson('/push/subscribe', $body)->assertCreated()->assertJson(['subscribed' => true]);
        $this->postJson('/push/subscribe', ['keys' => ['p256dh' => 'BKey2', 'auth' => 'a2']] + $body)->assertOk();
        $this->assertSame(1, PushSubscription::count());
        $this->assertSame(['BKey2', 'en'], [PushSubscription::first()->public_key, PushSubscription::first()->locale]);

        $this->postJson('/push/unsubscribe', ['endpoint' => $body['endpoint']])->assertOk()->assertJson(['subscribed' => false]);
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_only_known_push_services_over_https_are_accepted(): void
    {
        $keys = ['keys' => ['p256dh' => 'k', 'auth' => 'a']];
        foreach (['https://evil.example.com/x', 'http://fcm.googleapis.com/x', 'https://googleapis.com.evil.io/x', 'https://127.0.0.1/x', 'https://fcm.googleapis.com:8443/x'] as $bad) {
            $this->postJson('/push/subscribe', ['endpoint' => $bad] + $keys)->assertStatus(422);
        }
        foreach (['https://fcm.googleapis.com/fcm/send/1', 'https://updates.push.services.mozilla.com/wpush/v2/2', 'https://web.push.apple.com/3', 'https://wns2-par02p.notify.windows.com/w/?token=4'] as $good) {
            $this->postJson('/push/subscribe', ['endpoint' => $good] + $keys)->assertCreated();
        }
        $this->postJson('/push/subscribe', ['endpoint' => 'https://fcm.googleapis.com/x'])->assertStatus(422)->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
    }

    public function test_subscribing_is_off_without_keys_and_the_button_only_renders_with_keys(): void
    {
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('data-push-box', $html);
        $this->assertStringContainsString('<meta name="vapid-key" content="BPublicKeyForTests">', $html);
        $this->assertStringContainsString('<link rel="manifest" href="'.url('/manifest.webmanifest').'">', $html);
        $this->assertStringContainsString('Aktifkan notifikasi', $html);
        $this->assertStringContainsString('Turn on notifications', $this->get('/en')->getContent());

        config(['webpush.public_key' => null]);
        $this->assertStringNotContainsString('data-push-box', $this->get('/')->getContent());
        $this->postJson('/push/subscribe', ['endpoint' => 'https://fcm.googleapis.com/x', 'keys' => ['p256dh' => 'k', 'auth' => 'a']])->assertNotFound();
    }

    // ---- announcing new movies -----------------------------------------------------------

    public function test_a_movie_that_goes_live_is_announced_once_in_each_subscribers_language(): void
    {
        $this->subscribe('https://fcm.googleapis.com/fcm/send/id-user', 'id');
        $this->subscribe('https://fcm.googleapis.com/fcm/send/en-user', 'en');
        $movie = Movie::factory()->create(['title' => 'Night Train', 'synopsis' => 'A long ride.', 'thumbnail_url' => 'https://img.example.com/n.jpg']);

        $this->artisan('app:push-send')->expectsOutputToContain('queued 1, messages 1, sent 2')->assertSuccessful();

        $byEndpoint = collect($this->transport->sent)->keyBy('endpoint');
        $id = $byEndpoint['https://fcm.googleapis.com/fcm/send/id-user']['payload'];
        $en = $byEndpoint['https://fcm.googleapis.com/fcm/send/en-user']['payload'];
        $this->assertSame('Film baru: Night Train', $id['title']);
        $this->assertSame('A long ride.', $id['body']);
        $this->assertSame(url('/movies/night-train'), $id['url']);
        $this->assertSame('https://img.example.com/n.jpg', $id['image']);
        $this->assertSame(url('/icons/icon-192.png'), $id['icon']);
        $this->assertSame('New movie: Night Train', $en['title']);
        $this->assertSame(url('/en/movies/night-train'), $en['url']);

        $this->assertNotNull($movie->fresh()->push_notified_at);
        $message = PushMessage::first();
        $this->assertSame(['sent', 2, 0], [$message->status, $message->sent, $message->failed]);

        // Next run: nothing new.
        $this->artisan('app:push-send')->expectsOutputToContain('queued 0, messages 0, sent 0');
        $this->assertCount(2, $this->transport->sent);
    }

    public function test_scheduled_movies_are_announced_when_their_time_comes_and_drafts_never(): void
    {
        $this->subscribe();
        Movie::factory()->create(['title' => 'Later', 'published_at' => now()->addHour()]);
        Movie::factory()->create(['title' => 'Draft', 'published_at' => null]);

        $this->artisan('app:push-send')->expectsOutputToContain('queued 0');
        $this->assertSame([], $this->transport->sent);

        $this->travel(61)->minutes();
        $this->artisan('app:push-send')->expectsOutputToContain('queued 1, messages 1, sent 1');
        $this->assertSame('Film baru: Later', $this->transport->sent[0]['payload']['title']);
    }

    public function test_several_movies_at_once_become_one_summary_and_old_or_opted_out_ones_are_skipped(): void
    {
        $this->subscribe();
        Movie::factory()->create(['title' => 'Alpha', 'published_at' => now()->subMinutes(2)]);
        Movie::factory()->create(['title' => 'Bravo', 'published_at' => now()->subMinute()]);
        Movie::factory()->create(['title' => 'Quiet', 'push_notify' => false]);
        Movie::factory()->create(['title' => 'Ancient', 'published_at' => now()->subDays(3)]);

        $this->artisan('app:push-send')->expectsOutputToContain('queued 1, messages 1, sent 1');

        $p = $this->transport->sent[0]['payload'];
        $this->assertSame('2 film baru di MovieMov', $p['title']);
        $this->assertSame('Bravo, Alpha', $p['body']);
        $this->assertSame(url('/'), $p['url']);
        $this->assertSame(4, Movie::whereNotNull('push_notified_at')->count());   // all marked, only 2 announced
    }

    public function test_movies_live_before_this_feature_are_never_announced(): void
    {
        // The migration marks existing live movies; simulate one that was marked.
        Movie::factory()->create(['push_notified_at' => now()->subDay()]);
        $this->subscribe();

        $this->artisan('app:push-send')->expectsOutputToContain('queued 0');
        $this->assertSame([], $this->transport->sent);
    }

    public function test_expired_subscriptions_are_removed_and_failing_ones_after_three_tries(): void
    {
        $ok = $this->subscribe('https://fcm.googleapis.com/fcm/send/ok');
        $gone = $this->subscribe('https://fcm.googleapis.com/fcm/send/gone');
        $flaky = $this->subscribe('https://fcm.googleapis.com/fcm/send/flaky');
        $this->transport->results = ['https://fcm.googleapis.com/fcm/send/gone' => 'expired', 'https://fcm.googleapis.com/fcm/send/flaky' => 'failed'];

        PushMessage::create(['kind' => 'custom', 'title' => 'One', 'url' => '/']);
        $this->artisan('app:push-send')->expectsOutputToContain('sent 1, failed 1, expired 1');
        $this->assertModelMissing($gone);
        $this->assertSame(1, $flaky->fresh()->failures);
        $this->assertNotNull($ok->fresh()->last_sent_at);

        foreach (['Two', 'Three'] as $title) {
            PushMessage::create(['kind' => 'custom', 'title' => $title, 'url' => '/']);
            $this->artisan('app:push-send');
        }
        $this->assertModelMissing($flaky);
        $this->assertModelExists($ok);
    }

    public function test_an_interrupted_message_resumes_after_its_cursor(): void
    {
        $a = $this->subscribe('https://fcm.googleapis.com/fcm/send/a');
        $this->subscribe('https://fcm.googleapis.com/fcm/send/b');
        $message = PushMessage::create(['kind' => 'custom', 'title' => 'Resume', 'url' => '/']);
        $message->forceFill(['status' => 'sending', 'cursor' => $a->id, 'sent' => 1])->save();

        $this->artisan('app:push-send');

        $this->assertSame(['https://fcm.googleapis.com/fcm/send/b'], array_column($this->transport->sent, 'endpoint'));
        $this->assertSame(['sent', 2], [$message->fresh()->status, $message->fresh()->sent]);
    }

    public function test_command_does_nothing_without_keys(): void
    {
        config(['webpush.private_key' => null]);
        Movie::factory()->create();
        $this->subscribe();

        $this->artisan('app:push-send')->expectsOutputToContain('Push is off')->assertSuccessful();
        $this->assertSame([], $this->transport->sent);
        $this->assertSame(0, PushMessage::count());
    }

    // ---- admin + API ------------------------------------------------------------------

    public function test_admin_sends_a_custom_notification_and_sees_stats(): void
    {
        $this->subscribe();
        $this->get('/admin/push')->assertRedirect('/admin/login');
        $this->actingAs($this->admin());

        $this->get('/admin/push')->assertOk()->assertSee('subscribers')->assertSee('Sender has not run yet')->assertSee('Send to 1 subscriber');
        $this->post('/admin/push', ['title' => 'Film baru minggu ini', 'body' => '5 film baru.', 'url' => 'https://evil.example.com/'])
            ->assertSessionHasErrors('url');
        $this->post('/admin/push', ['title' => 'Film baru minggu ini', 'body' => '5 film baru.', 'url' => url('/category/drama?x=1')])
            ->assertRedirect('/admin/push')->assertSessionHas('status');

        $this->artisan('app:push-send');
        $p = $this->transport->sent[0]['payload'];
        $this->assertSame(['Film baru minggu ini', '5 film baru.', url('/category/drama?x=1')], [$p['title'], $p['body'], $p['url']]);
        $this->get('/admin/push')->assertSee('Sender ran')->assertSee('1 delivered');
    }

    public function test_movie_form_and_api_control_notify_subscribers(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin/movies/create')->assertSee('Notify push subscribers when it goes live');

        $this->post('/admin/movies', ['_admin_form' => '1', 'title' => 'Quiet One', 'embed_url' => 'https://a.b/e/1', 'status' => 'published', 'notify_subscribers' => '0']);
        $this->assertFalse(Movie::where('slug', 'quiet-one')->first()->push_notify);

        Sanctum::actingAs($this->admin());
        $this->postJson('/api/movies', ['title' => 'Loud One', 'embed_url' => 'https://a.b/e/2'])->assertCreated();
        $this->assertTrue(Movie::where('slug', 'loud-one')->first()->push_notify);
        $this->postJson('/api/movies', ['title' => 'Api Quiet', 'embed_url' => 'https://a.b/e/3', 'notify_subscribers' => false])->assertCreated();
        $this->assertFalse(Movie::where('slug', 'api-quiet')->first()->push_notify);

        $this->subscribe();
        $this->artisan('app:push-send');
        $this->get('/admin/movies/loud-one/edit')->assertSee('subscribers were notified');
        $this->get('/admin/movies/api-quiet/edit')->assertSee('went live without a notification');
    }

    public function test_push_api_is_admin_only(): void
    {
        $this->getJson('/api/push')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/push')->assertForbidden();
        $this->postJson('/api/push/messages', ['title' => 'x'])->assertForbidden();

        Sanctum::actingAs($this->admin());
        $this->subscribe();
        $this->postJson('/api/push/messages', ['title' => 'Hello', 'url' => '/tag/korea'])->assertCreated()
            ->assertJsonPath('data.kind', 'custom')->assertJsonPath('data.status', 'queued');
        $this->getJson('/api/push')->assertOk()->assertJsonPath('enabled', true)->assertJsonPath('subscribers', 1)
            ->assertJsonPath('messages.0.label', 'Hello');
        $this->postJson('/api/push/messages', ['title' => 'Bad', 'url' => '//evil.example.com'])->assertStatus(422);
    }

    public function test_push_keys_command_writes_only_missing_keys(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($file, "APP_NAME=MovieMov\n");

        $this->artisan('app:push-keys', ['--env-file' => $file])->expectsOutputToContain('Keys saved')->assertSuccessful();
        $env = file_get_contents($file);
        $this->assertMatchesRegularExpression('/^VAPID_PUBLIC_KEY=[A-Za-z0-9_-]{80,}$/m', $env);
        $this->assertMatchesRegularExpression('/^VAPID_PRIVATE_KEY=[A-Za-z0-9_-]{40,}$/m', $env);

        $this->artisan('app:push-keys', ['--env-file' => $file])->expectsOutputToContain('Keys already set');
        $this->assertSame($env, file_get_contents($file));
        unlink($file);
    }

    public function test_real_transport_builds_without_gmp_or_bcmath(): void
    {
        // Regression: without GMP/BCMath the library raised an E_USER_NOTICE that Laravel turned into an exception,
        // so nothing was ever sent. The real transport must construct (and send nothing) with real-format keys.
        $keys = VAPID::createVapidKeys();
        config(['webpush.public_key' => $keys['publicKey'], 'webpush.private_key' => $keys['privateKey']]);

        $this->assertSame([], (new WebPushTransport)->send([]));
    }

    public function test_service_worker_and_manifest_exist(): void
    {
        // public_path() points at a temp dir in tests (TestCase), so read the real files.
        $manifest = json_decode(file_get_contents(base_path('public/manifest.webmanifest')), true);
        $this->assertSame('standalone', $manifest['display']);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(base_path('public/'.ltrim($icon['src'], '/')));
        }
        $sw = file_get_contents(base_path('public/sw.js'));
        $this->assertStringContainsString("addEventListener('push'", $sw);
        $this->assertStringContainsString("addEventListener('notificationclick'", $sw);
        $this->assertTrue(PushDispatcher::enabled());
    }
}
