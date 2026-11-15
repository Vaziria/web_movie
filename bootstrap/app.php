<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['admin' => EnsureAdmin::class, 'locale' => SetLocale::class, 'track' => TrackActivity::class]);
        // Admin pages send guests to the admin login; visitor pages (account) to the visitor login of their language.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*')
            ? route('admin.login')
            : lroute('auth.login', [], $request->is('en', 'en/*') ? 'en' : 'id'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
