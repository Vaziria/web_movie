<?php

namespace App\Providers;

use App\Mail\PhpMailTransport;
use App\Services\Push\PushTransport;
use App\Services\Push\WebPushTransport;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PushTransport::class, WebPushTransport::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Canonical URLs, sitemap and og: tags must always be https in production, even if a proxy hides the scheme.
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // MAIL_MAILER=phpmail: PHP mail(), for hosts without proc_open (Hostinger shared hosting).
        Mail::extend('phpmail', fn () => new PhpMailTransport);

        // Password reset emails link to the admin panel's reset form.
        ResetPassword::createUrlUsing(fn ($user, string $token) => route('admin.password.reset', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]));
    }
}
