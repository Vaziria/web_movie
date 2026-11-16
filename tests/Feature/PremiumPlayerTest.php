<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use App\Services\Settings;
use App\Services\Stream\Googlebot;
use App\Services\Stream\VidaraStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PremiumPlayerTest extends TestCase
{
    use RefreshDatabase;

    private const GOOGLEBOT_UA = 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    private function enable(): void
    {
        app(Settings::class)->put(['premium_enabled' => '1']);
    }

    private function vidaraMovie(array $attrs = []): Movie
    {
        return Movie::factory()->create($attrs + ['embed_url' => 'https://vidara.to/e/AbCd12345']);
    }

    private function streamLink(int $expiresIn = 8 * 3600): string
    {
        return 'https://s1.video-cdn.example/hls/key123/master.m3u8?token=abc123-'.(now()->getTimestamp() + $expiresIn).'-10.0.0.1-sig';
    }

    private function fakeVidara(?string $url = null, int $status = 200): void
    {
        Http::fake(['vidara.to/api/stream' => Http::response(['filecode' => 'AbCd12345', 'streaming_url' => $url ?? $this->streamLink(), 'vast_ads' => ''], $status)]);
    }

    private function member(int $days = 10): User
    {
        $u = User::factory()->create();
        $u->forceFill(['premium_until' => now()->addDays($days)])->save();

        return $u;
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();

        return $u;
    }

    /** Googlebot check with fixed DNS answers (tests come from 127.0.0.1). */
    private function fakeDns(string $reverseHost, array $forwardIps = ['127.0.0.1']): void
    {
        $this->app->instance(Googlebot::class, new Googlebot(fn () => $reverseHost, fn () => $forwardIps));
    }

    // ---- vidara links -------------------------------------------------------------

    public function test_vidara_file_codes_are_read_from_any_player(): void
    {
        $this->assertSame('AbCd12345', VidaraStream::filecodeFromUrl('https://vidara.to/e/AbCd12345'));
        $this->assertSame('AbCd12345', VidaraStream::filecodeFromUrl('https://vidara.so/v/AbCd12345/'));
        $this->assertNull(VidaraStream::filecodeFromUrl('https://vidara.to.evil.example/e/AbCd12345'));
        $this->assertNull(VidaraStream::filecodeFromUrl('https://other-host.example/e/AbCd12345'));
        $this->assertNull(VidaraStream::filecodeFromUrl('https://vidara.to/e/../../x'));

        $backup = Movie::factory()->create(['embed_url' => 'https://other-host.example/e/x1', 'backup_embed_urls' => ['https://vidara.so/e/Backup777']]);
        $this->assertSame('Backup777', VidaraStream::filecode($backup));
        $this->assertNull(VidaraStream::filecode(Movie::factory()->create(['embed_url' => 'https://other-host.example/e/x1'])));
    }

    // ---- movie page -----------------------------------------------------------------

    public function test_off_by_default_nothing_changes(): void
    {
        $movie = $this->vidaraMovie();
        $this->get('/movies/'.$movie->slug)->assertOk()->assertDontSee('premium-player')->assertDontSee('contentUrl');
        $this->get('/stream/'.$movie->slug.'.m3u8')->assertNotFound();
        $this->get('/premium')->assertNotFound();
    }

    public function test_guest_sees_the_offer_on_top_the_free_player_below_and_paywall_markup(): void
    {
        $this->enable();
        $movie = $this->vidaraMovie();

        $html = $this->get('/movies/'.$movie->slug)->assertOk()
            ->assertSee('Tonton tanpa iklan')->assertSee('href="'.url('/premium').'"', false)
            ->assertSee('Pemutar gratis (dengan iklan)')->assertSee('src="'.$movie->embed_url.'"', false)
            ->assertDontSee('<video', false)->assertDontSee('Pemutar cadangan')
            ->getContent();

        $this->assertLessThan(strpos($html, '<iframe'), strpos($html, 'premium-player'));   // premium box first
        $this->assertStringContainsString('"contentUrl":"'.url('/stream/'.$movie->slug.'.m3u8').'"', $html);
        $this->assertStringContainsString('"isAccessibleForFree":false,"hasPart":{"@type":"WebPageElement","isAccessibleForFree":false,"cssSelector":".premium-player"}', $html);
        $this->assertStringContainsString('"embedUrl":"'.$movie->embed_url.'"', $html);
    }

    public function test_movies_without_a_vidara_player_have_no_premium_box(): void
    {
        $this->enable();
        $movie = Movie::factory()->create(['embed_url' => 'https://other-host.example/e/x1']);
        $this->get('/movies/'.$movie->slug)->assertOk()->assertDontSee('premium-player')->assertDontSee('contentUrl');
        $this->actingAs($this->member())->get('/stream/'.$movie->slug.'.m3u8')->assertNotFound();
    }

    public function test_members_and_admins_get_the_ad_free_player_with_the_free_one_folded(): void
    {
        $this->enable();
        $movie = $this->vidaraMovie();
        $stream = url('/stream/'.$movie->slug.'.m3u8');

        $this->actingAs($this->member())->get('/movies/'.$movie->slug)->assertOk()
            ->assertSee('<video', false)->assertSee('data-src="'.$stream.'"', false)
            ->assertSee('<source src="'.$stream.'" type="application/vnd.apple.mpegurl">', false)
            ->assertSee('Premium aktif sampai')->assertSee('Pemutar cadangan (gratis, dengan iklan)')
            ->assertSee('loading="lazy"', false)->assertDontSee('Dapatkan Premium');

        $this->actingAs($this->admin())->get('/movies/'.$movie->slug)->assertSee('<video', false)->assertDontSee('Premium aktif sampai');
    }

    public function test_expired_premium_is_locked_again(): void
    {
        $this->enable();
        $movie = $this->vidaraMovie();
        $user = $this->member(-1);

        $this->assertFalse($user->isPremium());
        $this->actingAs($user)->get('/movies/'.$movie->slug)->assertDontSee('<video', false)->assertSee('Dapatkan Premium');
        $this->actingAs($user)->get('/stream/'.$movie->slug.'.m3u8')->assertForbidden();
    }

    // ---- stream address -----------------------------------------------------------------

    public function test_stream_forwards_members_to_the_cached_vidara_link(): void
    {
        $this->enable();
        $this->fakeVidara($link = $this->streamLink());
        $movie = $this->vidaraMovie();

        $this->get('/stream/'.$movie->slug.'.m3u8')->assertForbidden();
        Http::assertNothingSent();

        $member = $this->member();
        $this->actingAs($member)->get('/stream/'.$movie->slug.'.m3u8')
            ->assertRedirect($link)->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($member)->get('/stream/'.$movie->slug.'.m3u8')->assertRedirect($link);

        Http::assertSentCount(1);   // cached
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://vidara.to/api/stream' && $r['filecode'] === 'AbCd12345'
            && str_contains($r->header('User-Agent')[0], 'MovieMov'));

        $this->travel(61)->minutes();
        $this->actingAs($member)->get('/stream/'.$movie->slug.'.m3u8')->assertRedirect();
        Http::assertSentCount(2);   // re-fetched after an hour
    }

    public function test_cache_never_outlives_the_links_own_expiry(): void
    {
        $this->enable();
        $this->fakeVidara($this->streamLink(15 * 60));   // expires in 15 minutes -> cached 5
        $movie = $this->vidaraMovie();
        $member = $this->member();

        $this->actingAs($member)->get('/stream/'.$movie->slug.'.m3u8');
        $this->travel(4)->minutes();
        $this->actingAs($member)->get('/stream/'.$movie->slug.'.m3u8');
        Http::assertSentCount(1);
        $this->travel(2)->minutes();
        $this->actingAs($member)->get('/stream/'.$movie->slug.'.m3u8');
        Http::assertSentCount(2);
    }

    public function test_vidara_failures_give_503_retry_later_and_show_in_settings(): void
    {
        $this->enable();
        $this->fakeVidara(null, 500);
        $movie = $this->vidaraMovie();
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/settings')->assertSee('No video link fetched from vidara yet');
        $this->actingAs($admin)->get('/stream/'.$movie->slug.'.m3u8')->assertStatus(503)->assertHeader('Retry-After', '60');
        $this->actingAs($admin)->get('/stream/'.$movie->slug.'.m3u8')->assertStatus(503);
        Http::assertSentCount(1);   // failure remembered for a minute
        $this->actingAs($admin)->get('/admin/settings')->assertSee('failed (HTTP 500)');
    }

    public function test_unusable_stream_links_are_never_forwarded(): void
    {
        $this->enable();
        $movie = $this->vidaraMovie();
        foreach (['http://cdn.example/master.m3u8', 'https://127.0.0.1/master.m3u8', 'https://cdn.example/page.html', 'javascript:alert(1)'] as $bad) {
            $this->assertFalse(VidaraStream::looksLikeStream($bad), $bad);
        }
        $this->fakeVidara('https://localhost/hls/master.m3u8');
        $this->actingAs($this->member())->get('/stream/'.$movie->slug.'.m3u8')->assertStatus(503);
    }

    public function test_drafts_have_no_stream(): void
    {
        $this->enable();
        $movie = $this->vidaraMovie(['published_at' => null]);
        $this->actingAs($this->member())->get('/stream/'.$movie->slug.'.m3u8')->assertNotFound();
    }

    // ---- Googlebot -------------------------------------------------------------------

    public function test_verified_googlebot_sees_the_unlocked_player_and_the_free_player_open(): void
    {
        $this->enable();
        $this->fakeVidara($link = $this->streamLink());
        $this->fakeDns('crawl-66-249-66-1.googlebot.com');
        $movie = $this->vidaraMovie();

        $this->withHeader('User-Agent', self::GOOGLEBOT_UA)->get('/movies/'.$movie->slug)->assertOk()
            ->assertSee('<video', false)->assertSee('Pemutar gratis (dengan iklan)')->assertDontSee('Pemutar cadangan');
        $this->withHeader('User-Agent', self::GOOGLEBOT_UA)->get('/stream/'.$movie->slug.'.m3u8')->assertRedirect($link);
    }

    public function test_a_fake_googlebot_is_treated_as_a_guest(): void
    {
        $this->enable();
        $this->fakeVidara();
        $movie = $this->vidaraMovie();

        $this->fakeDns('somebody.example.net');   // claims Googlebot, IP isn't Google's
        $this->withHeader('User-Agent', self::GOOGLEBOT_UA)->get('/movies/'.$movie->slug)->assertDontSee('<video', false)->assertSee('Dapatkan Premium');
        $this->withHeader('User-Agent', self::GOOGLEBOT_UA)->get('/stream/'.$movie->slug.'.m3u8')->assertForbidden();
        Http::assertNothingSent();

        // A Google IP with a normal browser user agent is just a visitor.
        $this->fakeDns('crawl-66-249-66-1.googlebot.com');
        $this->withHeader('User-Agent', 'Mozilla/5.0 Chrome/130')->get('/stream/'.$movie->slug.'.m3u8')->assertForbidden();
    }

    public function test_googlebot_verification_rules(): void
    {
        $ok = new Googlebot(fn () => 'crawl-66-249-66-1.googlebot.com', fn () => ['66.249.66.1']);
        $this->assertTrue($ok->verify('66.249.66.1'));
        $this->assertFalse($ok->verify('66.249.66.2'));   // forward lookup doesn't match

        $this->assertTrue((new Googlebot(fn () => 'rate-limited-proxy-66-249-91-1.google.com', fn () => ['66.249.91.1']))->verify('66.249.91.1'));
        $this->assertFalse((new Googlebot(fn () => 'crawl.googlebot.com.evil.example', fn () => ['1.2.3.4']))->verify('1.2.3.4'));
        $this->assertFalse((new Googlebot(fn () => '1.2.3.4', fn () => ['1.2.3.4']))->verify('1.2.3.4'));   // no PTR record
        $this->assertTrue((new Googlebot(fn () => 'crawl-2001-4860-4801-1.googlebot.com', fn () => ['2001:4860:4801:0:0:0:0:1']))->verify('2001:4860:4801::1'));
    }

    // ---- admin ----------------------------------------------------------------------

    public function test_admin_gives_extends_sets_and_removes_premium(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['email' => 'rina@example.com']);
        $url = '/admin/users/'.$user->id.'/premium';

        $this->actingAs($admin)->post($url, ['action' => 'add', 'days' => 30])->assertSessionHas('status');
        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), $user->fresh()->premium_until->getTimestamp(), 5);

        $this->actingAs($admin)->post($url, ['action' => 'add', 'days' => 30]);   // extends from the current end
        $this->assertEqualsWithDelta(now()->addDays(60)->getTimestamp(), $user->fresh()->premium_until->getTimestamp(), 5);

        $this->actingAs($admin)->get('/admin/users?filter=premium')->assertSee('rina@example.com')->assertSee('PREMIUM');
        $this->actingAs($admin)->get('/admin/users/'.$user->id)->assertSee('Premium (ad-free player)')->assertSee('until ');

        $date = now()->timezone(config('movies.admin_timezone'))->addDays(100)->toDateString();
        $this->actingAs($admin)->post($url, ['action' => 'set', 'until' => $date]);
        $this->assertSame($date, $user->fresh()->premium_until->timezone(config('movies.admin_timezone'))->toDateString());

        $this->actingAs($admin)->post($url, ['action' => 'set', 'until' => now()->subDay()->toDateString()])->assertSessionHasErrors('until');
        $this->actingAs($admin)->post($url, ['action' => 'add', 'days' => 0])->assertSessionHasErrors('days');

        $this->actingAs($admin)->post($url, ['action' => 'remove'])->assertSessionHas('status', 'Premium removed for rina@example.com.');
        $this->assertNull($user->fresh()->premium_until);
        $this->actingAs($admin)->get('/admin/users');   // shows (and clears) the confirmation message
        $this->actingAs($admin)->get('/admin/users?filter=premium')->assertDontSee('rina@example.com');

        $this->actingAs(User::factory()->create())->post($url, ['action' => 'add', 'days' => 30])->assertForbidden();

        $user->forceFill(['premium_until' => now()->addDays(5)])->save();
        $token = $admin->createToken('t')->plainTextToken;
        $this->app['auth']->forgetGuards();   // drop the web login from actingAs() above; use the token only
        $row = collect($this->withToken($token)->getJson('/api/users')->assertOk()->json('data'))->firstWhere('id', $user->id);
        $this->assertNotNull($row['premium_until']);
    }

    public function test_settings_switch(): void
    {
        $admin = $this->admin();
        $base = ['guest_free_movies' => '2', 'guest_limit_period' => 'total'];

        $this->actingAs($admin)->get('/admin/settings')->assertSee('Premium player (ad-free)');
        $this->actingAs($admin)->put('/admin/settings', $base + ['premium_enabled' => '1'])->assertRedirect('/admin/settings');
        $this->assertTrue(app(Settings::class)->premiumEnabled());
        $this->actingAs($admin)->put('/admin/settings', $base + ['premium_enabled' => '0']);
        $this->assertFalse(app(Settings::class)->premiumEnabled());
    }

    // ---- premium page, robots -------------------------------------------------------

    public function test_premium_page_shows_status_and_how_to_subscribe(): void
    {
        $this->enable();
        $this->get('/premium')->assertOk()->assertSee('Langganan segera dibuka.')
            ->assertSee('<meta name="robots" content="noindex, follow">', false);

        app(Settings::class)->put(['contact_email' => 'premium@example.com']);
        $this->get('/premium')->assertSee('premium@example.com')->assertSee(url('/masuk'), false);
        $this->get('/en/premium')->assertOk()->assertSee('Premium: watch without ads')->assertSee('To subscribe, contact us:');

        $user = User::factory()->create(['email' => 'budi@example.com']);
        $this->actingAs($user)->get('/premium')->assertSee('Sebutkan email akunmu: budi@example.com');
        $this->actingAs($this->member())->get('/premium')->assertSee('Premium aktif sampai');
    }

    public function test_robots_txt_lets_google_fetch_the_stream(): void
    {
        $this->get('/robots.txt')->assertOk()->assertDontSee('/stream');
    }
}
