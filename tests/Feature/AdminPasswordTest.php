<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PasswordResetController;
use App\Mail\PhpMailTransport;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class AdminPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $email = 'heri@example.com', string $password = 'old-password-1'): User
    {
        $u = User::factory()->create(['email' => $email, 'password' => $password]);
        $u->forceFill(['is_admin' => true])->save();

        return $u;
    }

    // ---- forgot password ----------------------------------------------------------

    public function test_login_page_links_to_forgot_password(): void
    {
        $this->get('/admin/login')->assertSee('href="'.url('/admin/forgot-password').'"', false)->assertSee('Forgot password?');
        $this->get('/admin/forgot-password')->assertOk()->assertSee('Email me a reset link');
    }

    public function test_admin_gets_a_reset_link_to_the_admin_reset_form(): void
    {
        Notification::fake();
        $admin = $this->admin();

        $this->from('/admin/forgot-password')->post('/admin/forgot-password', ['email' => 'heri@example.com'])
            ->assertRedirect('/admin/forgot-password')->assertSessionHas('status', PasswordResetController::SENT);

        Notification::assertSentTo($admin, ResetPassword::class, function (ResetPassword $n) use ($admin) {
            $url = $n->toMail($admin)->actionUrl;

            return str_starts_with($url, url('/admin/reset-password/'.$n->token)) && str_contains($url, 'email=heri%40example.com');
        });
    }

    public function test_unknown_and_non_admin_emails_get_nothing_and_the_same_answer(): void
    {
        Notification::fake();
        $visitor = User::factory()->create(['email' => 'visitor@example.com']);

        foreach (['visitor@example.com', 'nobody@example.com'] as $email) {
            $this->from('/admin/forgot-password')->post('/admin/forgot-password', ['email' => $email])
                ->assertSessionHas('status', PasswordResetController::SENT);
        }
        Notification::assertNothingSent();
        Notification::assertNotSentTo($visitor, ResetPassword::class);
    }

    public function test_forgot_password_is_throttled(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post('/admin/forgot-password', ['email' => "x{$i}@example.com"])->assertRedirect();
        }
        $this->post('/admin/forgot-password', ['email' => 'x6@example.com'])->assertStatus(429);
    }

    // ---- reset --------------------------------------------------------------------

    public function test_reset_link_sets_the_new_password_logs_in_and_works_only_once(): void
    {
        $admin = $this->admin();
        $token = Password::createToken($admin);

        $this->get("/admin/reset-password/{$token}?email=heri%40example.com")->assertOk()
            ->assertSee('value="heri@example.com"', false)->assertSee('value="'.$token.'"', false);

        $this->post('/admin/reset-password', [
            'token' => $token, 'email' => 'heri@example.com',
            'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect('/admin/movies')->assertSessionHas('status', 'Password changed. You are logged in.');

        $this->assertAuthenticatedAs($admin);
        $this->assertTrue(Hash::check('brand-new-pass', $admin->fresh()->password));

        // Log out, then the same link again: refused.
        $this->post('/admin/logout');
        $this->post('/admin/reset-password', [
            'token' => $token, 'email' => 'heri@example.com',
            'password' => 'another-pass-2', 'password_confirmation' => 'another-pass-2',
        ])->assertSessionHasErrors(['email' => 'This reset link is invalid or has expired. Request a new one.']);
        $this->assertTrue(Hash::check('brand-new-pass', $admin->fresh()->password));

        // And the new password logs in.
        $this->post('/admin/login', ['email' => 'heri@example.com', 'password' => 'brand-new-pass'])->assertRedirect('/admin/movies');
    }

    public function test_reset_rejects_bad_token_short_or_unconfirmed_password(): void
    {
        $admin = $this->admin();
        $token = Password::createToken($admin);
        $base = ['token' => $token, 'email' => 'heri@example.com'];

        $this->post('/admin/reset-password', ['token' => 'wrong'] + $base + ['password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertSessionHasErrors('email');
        $this->post('/admin/reset-password', $base + ['password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $this->post('/admin/reset-password', $base + ['password' => 'brand-new-pass', 'password_confirmation' => 'different-pass'])
            ->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertTrue(Hash::check('old-password-1', $admin->fresh()->password));
    }

    public function test_reset_never_changes_a_non_admin_password(): void
    {
        $visitor = User::factory()->create(['email' => 'visitor@example.com', 'password' => 'visitor-pass']);
        $token = Password::createToken($visitor);

        $this->post('/admin/reset-password', [
            'token' => $token, 'email' => 'visitor@example.com',
            'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertTrue(Hash::check('visitor-pass', $visitor->fresh()->password));
    }

    // ---- account page ---------------------------------------------------------------

    public function test_account_page_changes_password_and_email_with_the_current_password(): void
    {
        $this->get('/admin/account')->assertRedirect('/admin/login');

        $admin = $this->admin();
        User::factory()->create(['email' => 'taken@example.com']);
        $this->actingAs($admin);
        $this->get('/admin/account')->assertOk()->assertSee('value="heri@example.com"', false);

        $this->put('/admin/account/password', ['current_password' => 'wrong', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertSessionHasErrors('current_password');
        $this->put('/admin/account/password', ['current_password' => 'old-password-1', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertSessionHas('status', 'Password changed.');
        $this->assertTrue(Hash::check('brand-new-pass', $admin->fresh()->password));

        $this->put('/admin/account/email', ['email' => 'taken@example.com', 'current_password' => 'brand-new-pass'])->assertSessionHasErrors('email');
        $this->put('/admin/account/email', ['email' => 'new@example.com', 'current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->put('/admin/account/email', ['email' => 'new@example.com', 'current_password' => 'brand-new-pass'])->assertSessionHasNoErrors();
        $this->assertSame('new@example.com', $admin->fresh()->email);
    }

    public function test_admin_email_command(): void
    {
        $this->admin('admin@moviemov.id');
        User::factory()->create(['email' => 'taken@example.com']);

        $this->artisan('app:admin-email', ['current' => 'nobody@x.id', 'new' => 'a@b.id'])->assertFailed();
        $this->artisan('app:admin-email', ['current' => 'admin@moviemov.id', 'new' => 'not-an-email'])->assertFailed();
        $this->artisan('app:admin-email', ['current' => 'admin@moviemov.id', 'new' => 'taken@example.com'])->assertFailed();
        $this->artisan('app:admin-email', ['current' => 'admin@moviemov.id', 'new' => 'heri@example.com'])
            ->expectsOutput('Admin login email is now heri@example.com')->assertSuccessful();
        $this->assertTrue(User::where('email', 'heri@example.com')->first()->is_admin);
    }

    // ---- PHP mail() transport -------------------------------------------------------

    public function test_phpmail_transport_passes_to_and_subject_separately(): void
    {
        $calls = [];
        $transport = new PhpMailTransport(function (...$args) use (&$calls) {
            $calls[] = $args;

            return true;
        });

        $transport->send((new Email)->from('MovieMov <noreply@moviemov.id>')->to('heri@example.com')
            ->subject('Reset Password Notification')->text("Hello\nClick the link."));

        [$to, $subject, $body, $headers, $params] = $calls[0];
        $this->assertSame('heri@example.com', $to);
        $this->assertSame('Reset Password Notification', $subject);
        $this->assertStringContainsString('Click the link.', $body);
        $this->assertStringContainsString('From: MovieMov <noreply@moviemov.id>', $headers);
        $this->assertStringContainsString('MIME-Version: 1.0', $headers);
        $this->assertDoesNotMatchRegularExpression('/^(To|Subject):/mi', $headers);
        $this->assertSame('-fnoreply@moviemov.id', $params);
    }

    public function test_phpmail_transport_reports_a_refused_message(): void
    {
        $this->expectException(TransportException::class);
        (new PhpMailTransport(fn () => false))->send((new Email)->from('a@b.id')->to('c@d.id')->subject('x')->text('y'));
    }

    public function test_phpmail_mailer_is_registered(): void
    {
        $this->assertInstanceOf(PhpMailTransport::class, Mail::mailer('phpmail')->getSymfonyTransport());
    }

    public function test_split_unfolds_long_headers(): void
    {
        $raw = "From: a@b.id\r\nTo: x@y.id,\r\n z@y.id\r\nSubject: =?utf-8?Q?a?=\r\n =?utf-8?Q?b?=\r\nX-Long: one\r\n two\r\n\r\nBody\r\n\r\nMore";
        $parts = PhpMailTransport::split($raw);
        $this->assertSame('x@y.id, z@y.id', $parts['to']);
        $this->assertSame('=?utf-8?Q?a?= =?utf-8?Q?b?=', $parts['subject']);
        $this->assertSame("From: a@b.id\r\nX-Long: one\r\n two", $parts['headers']);
        $this->assertSame("Body\r\n\r\nMore", $parts['body']);
    }
}
