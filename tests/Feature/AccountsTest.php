<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use App\Services\Settings;
use App\Services\Visitors;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class AccountsTest extends TestCase
{
    use RefreshDatabase;

    private const LOCKED = 'Masuk untuk lanjut menonton';

    private function admin(): User
    {
        $u = User::factory()->create(['email' => 'admin@example.com']);
        $u->forceFill(['is_admin' => true])->save();

        return $u;
    }

    /** Open a movie as a guest carrying the free-movies cookie from the previous response (like a browser). */
    private function openAsGuest(Movie $movie, ?string $cookie): array
    {
        $req = $cookie !== null ? $this->withCookie(Visitors::SEEN_COOKIE, $cookie) : $this;
        $res = $req->get('/movies/'.$movie->slug);
        $res->assertOk();
        $next = $res->getCookie(Visitors::SEEN_COOKIE)?->getValue() ?? $cookie;

        return [$res, $next];
    }

    private function enableGoogle(): void
    {
        app(Settings::class)->put(['google_client_id' => '123-abc.apps.googleusercontent.com', 'google_client_secret' => 'test-secret', 'google_enabled' => '1']);
    }

    private function fakeGoogleUser(string $id, string $email, string $name = 'Budi Santoso', bool $verified = true): void
    {
        $g = (new GoogleUser)->setRaw(['email_verified' => $verified])->map([
            'id' => $id, 'name' => $name, 'email' => $email, 'avatar' => 'https://lh3.googleusercontent.com/a/x',
        ]);
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->andReturn($g);
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth?client_id=x'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    // ---- guest limit --------------------------------------------------------------

    public function test_guest_gets_two_free_movies_then_the_player_asks_to_log_in(): void
    {
        [$a, $b, $c] = Movie::factory()->count(3)->create();

        [$r1, $cookie] = $this->openAsGuest($a, null);
        $r1->assertSee($a->embed_url, false)->assertSee('Sisa film gratis: 1')->assertDontSee(self::LOCKED);

        [$r2, $cookie] = $this->openAsGuest($b, $cookie);
        $r2->assertSee($b->embed_url, false)->assertSee('Ini film gratis terakhirmu.');

        [$r3, $cookie] = $this->openAsGuest($c, $cookie);
        $r3->assertSee(self::LOCKED)->assertDontSee('<iframe', false)
            ->assertSee(e(lroute('auth.register', ['next' => '/movies/'.$c->slug])), false);

        // Re-opening a movie they already got for free never counts again.
        [$again] = $this->openAsGuest($a, $cookie);
        $again->assertSee($a->embed_url, false)->assertDontSee(self::LOCKED);
    }

    public function test_locked_page_still_has_full_seo_and_views_are_not_counted(): void
    {
        [$a, $b, $c] = Movie::factory()->count(3)->create();
        [, $cookie] = $this->openAsGuest($a, null);
        [, $cookie] = $this->openAsGuest($b, $cookie);
        [$locked] = $this->openAsGuest($c, $cookie);

        // Same page for search engines: still indexable, VideoObject with the embed URL (Google always gets the player).
        $locked->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
            ->assertSee('"@type":"VideoObject"', false)->assertSee('"embedUrl":"'.$c->embed_url.'"', false);
        $this->assertSame(0, (int) $c->fresh()->views);
    }

    public function test_a_fresh_visitor_such_as_a_search_crawler_always_sees_the_player(): void
    {
        $movies = Movie::factory()->count(4)->create();
        foreach ($movies as $m) {   // no cookie sent back = brand-new visitor every time
            $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
                ->get('/movies/'.$m->slug)->assertSee($m->embed_url, false)->assertDontSee(self::LOCKED);
        }
    }

    public function test_logged_in_users_watch_without_limits_and_watches_are_recorded(): void
    {
        $user = User::factory()->create();
        $movies = Movie::factory()->count(4)->create();
        foreach ($movies as $m) {
            $this->actingAs($user)->get('/movies/'.$m->slug)->assertSee($m->embed_url, false)->assertDontSee('Sisa film gratis');
        }
        $this->actingAs($user)->get('/movies/'.$movies[0]->slug);
        $this->assertSame(4, DB::table('movie_watches')->where('user_id', $user->id)->count());
    }

    public function test_per_day_limit_starts_again_the_next_day_and_zero_means_no_limit(): void
    {
        app(Settings::class)->put(['guest_free_movies' => '1', 'guest_limit_period' => 'day']);
        [$a, $b] = Movie::factory()->count(2)->create();

        [, $cookie] = $this->openAsGuest($a, null);
        [$locked] = $this->openAsGuest($b, $cookie);
        $locked->assertSee(self::LOCKED)->assertSee('hari ini');

        $this->travel(1)->days();
        [$nextDay] = $this->openAsGuest($b, $cookie);
        $nextDay->assertSee($b->embed_url, false);

        app(Settings::class)->put(['guest_free_movies' => '0']);
        [$free] = $this->openAsGuest($a, json_encode(['d' => 'x', 'ids' => [999, 998]]));
        $free->assertSee($a->embed_url, false)->assertDontSee('Sisa film gratis');
    }

    // ---- register / login / logout ---------------------------------------------------

    public function test_register_logs_in_and_returns_to_the_movie(): void
    {
        $movie = Movie::factory()->create();
        $this->get('/daftar?next=/movies/'.$movie->slug)->assertOk()->assertSee('Daftar gratis')
            ->assertSee('<meta name="robots" content="noindex, follow">', false);

        $this->post('/daftar', ['name' => 'Siti', 'email' => 'Siti@Example.com', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
            ->assertRedirect(url('/movies/'.$movie->slug));

        $user = User::where('email', 'siti@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse((bool) $user->is_admin);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_register_rejects_duplicates_bots_and_offsite_next(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $this->post('/daftar', ['name' => 'X', 'email' => 'taken@example.com', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
            ->assertSessionHasErrors('email');
        $this->post('/daftar', ['name' => 'Bot', 'email' => 'bot@example.com', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123', 'website' => 'http://spam'])
            ->assertStatus(422);
        $this->assertDatabaseMissing('users', ['email' => 'bot@example.com']);

        $this->get('/masuk?next=//evil.example/x');
        $this->post('/daftar', ['name' => 'Ok', 'email' => 'ok@example.com', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
            ->assertRedirect(url('/'));
    }

    public function test_login_logout_and_english_pages(): void
    {
        $user = User::factory()->create(['email' => 'budi@example.com', 'password' => 'rahasia123']);

        $this->post('/masuk', ['email' => 'budi@example.com', 'password' => 'salah-salah'])->assertSessionHasErrors(['email' => 'Email atau password salah.']);
        $this->assertGuest();

        $this->post('/en/login', ['email' => 'budi@example.com', 'password' => 'rahasia123'])->assertRedirect(url('/en'));
        $this->assertAuthenticatedAs($user);
        $this->get('/en')->assertSee('aria-label="My account"', false);

        $this->post('/en/logout')->assertRedirect(url('/en'));
        $this->assertGuest();
        $this->get('/en/login')->assertOk()->assertSee('Log in to watch every movie without limits.');
        $this->get('/')->assertSee(url('/masuk'), false)->assertSee('>Masuk</a>', false);
    }

    public function test_account_pages_need_login_in_the_right_language(): void
    {
        $this->get('/akun')->assertRedirect(url('/masuk'));
        $this->get('/en/account')->assertRedirect(url('/en/login'));
        $this->get('/admin')->assertRedirect(url('/admin/login'));
    }

    public function test_blocked_users_cannot_log_in_and_are_logged_out(): void
    {
        $user = User::factory()->create(['email' => 'b@example.com', 'password' => 'rahasia123']);
        $user->forceFill(['blocked_at' => now()])->save();

        $this->post('/masuk', ['email' => 'b@example.com', 'password' => 'rahasia123'])->assertSessionHasErrors(['email' => 'Akun ini telah diblokir.']);
        $this->assertGuest();

        $this->actingAs($user)->get('/')->assertRedirect(url('/masuk'));
        $this->assertGuest();
    }

    // ---- account page -------------------------------------------------------------

    public function test_account_change_name_password_and_delete(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);
        $this->actingAs($user)->get('/akun')->assertOk()->assertSee($user->email)->assertSee('Hapus akun saya')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->actingAs($user)->put('/akun/nama', ['name' => 'Nama Baru'])->assertSessionHas('status');
        $this->assertSame('Nama Baru', $user->fresh()->name);

        $this->actingAs($user)->put('/akun/password', ['current_password' => 'salah', 'password' => 'baru12345', 'password_confirmation' => 'baru12345'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put('/akun/password', ['current_password' => 'rahasia123', 'password' => 'baru12345', 'password_confirmation' => 'baru12345'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('baru12345', $user->fresh()->password));

        $this->actingAs($user)->delete('/akun', ['confirm' => 'nope', 'password' => 'baru12345'])->assertSessionHasErrors('confirm');
        $this->actingAs($user)->delete('/akun', ['confirm' => 'hapus', 'password' => 'baru12345'])->assertRedirect(url('/'));
        $this->assertModelMissing($user);
        $this->assertGuest();
    }

    public function test_google_only_account_can_add_a_first_password(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'g-1']);
        $this->actingAs($user)->get('/akun')->assertSee('Buat password')->assertDontSee('Password saat ini');
        $this->actingAs($user)->put('/akun/password', ['password' => 'baru12345', 'password_confirmation' => 'baru12345'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('baru12345', $user->fresh()->password));
    }

    // ---- forgot password (visitors) -------------------------------------------------

    public function test_visitor_reset_link_goes_to_the_visitor_form_and_logs_in(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'v@example.com']);

        $this->post('/lupa-password', ['email' => 'v@example.com'])->assertSessionHas('status');
        $this->post('/lupa-password', ['email' => 'nobody@example.com'])->assertSessionHas('status');   // same answer

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user, &$token) {
            $token = $n->token;

            return str_starts_with($n->toMail($user)->actionUrl, url('/atur-password/'.$n->token));
        });

        $this->get('/atur-password/'.$token.'?email=v@example.com')->assertOk()->assertSee('Buat password baru');
        $this->post('/atur-password', ['token' => $token, 'email' => 'v@example.com', 'password' => 'baru12345', 'password_confirmation' => 'baru12345'])
            ->assertRedirect(url('/'));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('baru12345', $user->fresh()->password));
    }

    public function test_visitor_forgot_form_never_resets_an_admin(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->post('/lupa-password', ['email' => $admin->email]);
        Notification::assertNothingSent();

        $token = Password::broker()->createToken($admin);
        $this->post('/atur-password', ['token' => $token, 'email' => $admin->email, 'password' => 'hacked123', 'password_confirmation' => 'hacked123'])
            ->assertSessionHasErrors('email');
        $this->assertFalse(Hash::check('hacked123', $admin->fresh()->password));
    }

    // ---- Google login ---------------------------------------------------------------

    public function test_google_button_and_routes_are_off_until_configured(): void
    {
        $this->get('/masuk')->assertDontSee('Lanjutkan dengan Google');
        $this->get('/auth/google')->assertNotFound();

        $this->enableGoogle();
        $this->get('/masuk')->assertSee('Lanjutkan dengan Google');
    }

    public function test_google_login_creates_a_verified_user_and_returns_to_the_movie(): void
    {
        $this->enableGoogle();
        $movie = Movie::factory()->create();
        $this->fakeGoogleUser('g-42', 'Budi@Gmail.com');

        $this->get('/auth/google?next=/en/movies/'.$movie->slug.'&lang=en')->assertRedirect();
        $this->get('/auth/google/callback?code=abc&state=x')->assertRedirect(url('/en/movies/'.$movie->slug));

        $user = User::where('google_id', 'g-42')->firstOrFail();
        $this->assertSame('budi@gmail.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->hasPassword());
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_login_links_an_existing_email_account_and_respects_blocks(): void
    {
        $this->enableGoogle();
        $existing = User::factory()->create(['email' => 'siti@gmail.com', 'password' => 'rahasia123']);
        $this->fakeGoogleUser('g-7', 'siti@gmail.com');
        $this->get('/auth/google/callback?code=abc');
        $this->assertSame('g-7', $existing->fresh()->google_id);
        $this->assertSame(1, User::count());

        auth()->logout();
        $existing->forceFill(['blocked_at' => now()])->save();
        $this->get('/auth/google/callback?code=abc')->assertRedirect(url('/masuk'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_google_cancel_and_unverified_email(): void
    {
        $this->enableGoogle();
        $this->get('/auth/google/callback?error=access_denied')->assertRedirect(url('/masuk'));
        $this->fakeGoogleUser('g-9', 'x@gmail.com', verified: false);
        $this->get('/auth/google/callback?code=abc')->assertSessionHasErrors('email');
        $this->assertSame(0, User::count());
    }

    // ---- admin: settings, users, stats ---------------------------------------------------

    public function test_admin_settings_store_the_google_secret_encrypted_and_never_show_it(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put('/admin/settings', [
            'google_client_id' => '680699783344-abc.apps.googleusercontent.com',
            'google_client_secret' => 'GOCSPX-super-secret',
            'google_enabled' => '1',
            'guest_free_movies' => '3',
            'guest_limit_period' => 'day',
            'contact_email' => 'privacy@example.com',
        ])->assertRedirect('/admin/settings');

        $raw = DB::table('settings')->where('key', 'google_client_secret')->value('value');
        $this->assertNotEmpty($raw);
        $this->assertStringNotContainsString('super-secret', $raw);
        $s = app(Settings::class);
        $this->assertSame('GOCSPX-super-secret', $s->get('google_client_secret'));
        $this->assertTrue($s->googleEnabled());
        $this->assertSame(3, $s->guestFreeMovies());
        $this->assertSame('day', $s->guestLimitPeriod());

        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertDontSee('super-secret')->assertSee(url('/auth/google/callback'));

        // Empty box keeps the secret; "remove" clears it.
        $base = ['google_client_id' => '680699783344-abc.apps.googleusercontent.com', 'google_enabled' => '1', 'guest_free_movies' => '2', 'guest_limit_period' => 'total'];
        $this->actingAs($admin)->put('/admin/settings', $base + ['google_client_secret' => '']);
        $this->assertTrue(app(Settings::class)->has('google_client_secret'));
        $this->actingAs($admin)->put('/admin/settings', $base + ['remove_google_secret' => '1']);
        $this->assertFalse(app(Settings::class)->googleEnabled());

        $this->actingAs($admin)->put('/admin/settings', ['google_client_id' => 'not-a-client-id', 'guest_free_movies' => '2', 'guest_limit_period' => 'total'])
            ->assertSessionHasErrors('google_client_id');
        $this->actingAs(User::factory()->create())->get('/admin/settings')->assertForbidden();
    }

    public function test_admin_users_list_block_unblock_reset_and_delete(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Rina Wati', 'email' => 'rina@example.com']);
        User::factory()->create(['name' => 'Joko', 'google_id' => 'g-1']);

        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('Rina Wati')->assertSee('Joko')->assertSee('ADMIN');
        $this->actingAs($admin)->get('/admin/users?q=rina')->assertSee('Rina Wati')->assertDontSee('Joko');
        $this->actingAs($admin)->get('/admin/users?filter=google')->assertSee('Joko')->assertDontSee('Rina Wati');
        $this->actingAs($admin)->get('/admin/users/'.$user->id)->assertOk()->assertSee('rina@example.com');

        $this->actingAs($admin)->post('/admin/users/'.$user->id.'/block')->assertSessionHas('status');
        $this->assertTrue($user->fresh()->isBlocked());
        $this->actingAs($admin)->post('/admin/users/'.$user->id.'/unblock');
        $this->assertFalse($user->fresh()->isBlocked());

        $this->actingAs($admin)->post('/admin/users/'.$user->id.'/reset-link')->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class, fn (ResetPassword $n) => str_contains($n->toMail($user)->actionUrl, '/atur-password/'));

        $this->actingAs($admin)->post('/admin/users/'.$admin->id.'/block')->assertForbidden();
        $this->actingAs($admin)->delete('/admin/users/'.$admin->id)->assertForbidden();

        $this->actingAs($admin)->delete('/admin/users/'.$user->id)->assertRedirect('/admin/users');
        $this->assertModelMissing($user);
    }

    public function test_daily_active_counts_users_and_returning_guests_but_not_crawlers(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/')->assertOk();
        $this->actingAs($user)->get('/');   // once per day
        $this->assertNotNull($user->fresh()->last_active_at);
        auth()->logout();
        $this->flushSession();

        // Guest: first visit only sets the visitor cookie; counted once it comes back.
        $first = $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14) Chrome/130')->get('/');
        $vid = $first->getCookie(Visitors::VISITOR_COOKIE)->getValue();
        $this->withCookie(Visitors::VISITOR_COOKIE, $vid)->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14) Chrome/130')->get('/');
        $this->flushSession();
        $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/');

        $rows = DB::table('daily_actives')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows->where('is_user', true)->count());
        $this->assertSame('g:'.$vid, $rows->firstWhere('is_user', false)->visitor);
    }

    public function test_admin_stats_page_and_api(): void
    {
        $admin = $this->admin();
        $today = now()->timezone(config('movies.admin_timezone'))->toDateString();
        DB::table('daily_actives')->insert([
            ['day' => $today, 'visitor' => 'u:1', 'is_user' => true],
            ['day' => $today, 'visitor' => 'g:aaaaaaaaaaaaaaaaaaaaaaaa', 'is_user' => false],
            ['day' => $today, 'visitor' => 'g:bbbbbbbbbbbbbbbbbbbbbbbb', 'is_user' => false],
        ]);

        $this->actingAs($admin)->get('/admin/stats')->assertOk()->assertSee('Daily active users')->assertSee('1 user · 2 guests');

        $token = $admin->createToken('t')->plainTextToken;
        $json = $this->withToken($token)->getJson('/api/stats')->assertOk()->json();
        $this->assertCount(30, $json['daily']);
        $this->assertCount(12, $json['monthly']);
        $this->assertSame(['day' => $today, 'users' => 1, 'guests' => 2, 'signups' => 1], end($json['daily']));
        $this->assertSame(2, end($json['monthly'])['guests']);

        $this->withToken($token)->getJson('/api/users')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.is_admin', true);
        $plain = User::factory()->create()->createToken('t')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($plain)->getJson('/api/stats')->assertForbidden();
    }

    // ---- privacy page -------------------------------------------------------------

    public function test_privacy_page_in_both_languages_linked_and_in_the_sitemap(): void
    {
        app(Settings::class)->put(['contact_email' => 'privacy@example.com']);
        $this->get('/privasi')->assertOk()->assertSee('Kebijakan Privasi')->assertSee('UU No. 27/2022')->assertSee('privacy@example.com')
            ->assertSee('hreflang="en" href="'.url('/en/privacy').'"', false);
        $this->get('/en/privacy')->assertOk()->assertSee('What we collect');
        $this->get('/')->assertSee('href="'.url('/privasi').'"', false);
        $this->get('/sitemap.xml')->assertSee(url('/privasi'))->assertSee(url('/en/privacy'));
    }
}
