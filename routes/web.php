<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AccountController as AdminAccount;
use App\Http\Controllers\Admin\AuthController as AdminAuth;
use App\Http\Controllers\Admin\CategoryController as AdminCategories;
use App\Http\Controllers\Admin\MovieController as AdminMovies;
use App\Http\Controllers\Admin\PasswordResetController as AdminPasswordReset;
use App\Http\Controllers\Admin\PushController as AdminPush;
use App\Http\Controllers\Admin\ReportController as AdminReports;
use App\Http\Controllers\Admin\SettingsController as AdminSettings;
use App\Http\Controllers\Admin\StatsController as AdminStats;
use App\Http\Controllers\Admin\TagController as AdminTags;
use App\Http\Controllers\Admin\UserController as AdminUsers;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PremiumController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\StreamController;
use Illuminate\Support\Facades\Route;

/*
 * Public site in two languages: Indonesian at / (route names "movies.*") and English under /en ("en.movies.*").
 * Build links with lroute(), which picks the current locale.
 */
$public = function (string $l) {
    // Visitor accounts. Paths are translated; names are the same in both languages (lroute picks the locale).
    $p = fn (string $id, string $en) => $l === 'en' ? $en : $id;
    Route::get($p('/masuk', '/login'), [LoginController::class, 'show'])->name('auth.login');
    Route::post($p('/masuk', '/login'), [LoginController::class, 'login'])->middleware('throttle:5,1')->name('auth.login.submit');
    Route::get($p('/daftar', '/register'), [LoginController::class, 'showRegister'])->name('auth.register');
    Route::post($p('/daftar', '/register'), [LoginController::class, 'register'])->middleware('throttle:5,1')->name('auth.register.submit');
    Route::post($p('/keluar', '/logout'), [LoginController::class, 'logout'])->name('auth.logout');
    Route::get($p('/lupa-password', '/forgot-password'), [PasswordController::class, 'request'])->name('auth.password.request');
    Route::post($p('/lupa-password', '/forgot-password'), [PasswordController::class, 'email'])->middleware('throttle:5,1')->name('auth.password.email');
    Route::get($p('/atur-password/{token}', '/reset-password/{token}'), [PasswordController::class, 'edit'])->name('auth.password.reset');
    Route::post($p('/atur-password', '/reset-password'), [PasswordController::class, 'update'])->middleware('throttle:5,1')->name('auth.password.update');
    Route::middleware('auth')->group(function () use ($p) {
        Route::get($p('/akun', '/account'), [AccountController::class, 'show'])->name('account');
        Route::put($p('/akun/nama', '/account/name'), [AccountController::class, 'updateName'])->name('account.name');
        Route::put($p('/akun/password', '/account/password'), [AccountController::class, 'updatePassword'])->middleware('throttle:10,1')->name('account.password');
        Route::delete($p('/akun', '/account'), [AccountController::class, 'destroy'])->middleware('throttle:5,1')->name('account.destroy');
    });
    Route::get($p('/privasi', '/privacy'), [PageController::class, 'privacy'])->name('pages.privacy');
    Route::get('/premium', [PremiumController::class, 'show'])->name('premium');

    Route::get('/', [MovieController::class, 'index'])->name('movies.index');
    Route::get('/category/{category}', [MovieController::class, 'category'])->name('movies.category');
    Route::get('/tag/{tag}', [MovieController::class, 'tag'])->name('movies.tag');
    Route::get('/movies/{movie}', [MovieController::class, 'show'])->name('movies.show');
    Route::post('/movies/{movie}/rate', [MovieController::class, 'rate'])
        ->middleware('throttle:20,1')
        ->name('movies.rate');
    Route::post('/movies/{movie}/report', [MovieController::class, 'report'])
        ->middleware('throttle:5,1')
        ->name('movies.report');
};
Route::middleware(['locale:id', 'track'])->group(fn () => $public('id'));
Route::prefix('en')->name('en.')->middleware(['locale:en', 'track'])->group(fn () => $public('en'));

// Premium player / Google's video file: fixed address that forwards to the current vidara link.
Route::get('/stream/{movie}.m3u8', [StreamController::class, 'show'])->middleware('throttle:60,1')->name('stream.show');

// Google login: one callback URL for both languages (the language is kept in the session).
Route::get('/auth/google', [GoogleController::class, 'redirect'])->middleware('throttle:10,1')->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->middleware('throttle:10,1')->name('auth.google.callback');

// Browser push subscriptions (resources/js/push.js). Same URL for both languages; the body carries the locale.
Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->middleware('throttle:10,1')->name('push.subscribe');
Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->middleware('throttle:10,1')->name('push.unsubscribe');

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

// Admin panel (session login; admins only; noindex; English)
Route::prefix('admin')->name('admin.')->middleware('locale:en')->group(function () {
    Route::get('/login', [AdminAuth::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuth::class, 'login'])->middleware('throttle:5,1')->name('login.submit');
    Route::get('/forgot-password', [AdminPasswordReset::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [AdminPasswordReset::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [AdminPasswordReset::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [AdminPasswordReset::class, 'update'])->middleware('throttle:5,1')->name('password.update');

    Route::middleware('admin')->group(function () {
        Route::post('/logout', [AdminAuth::class, 'logout'])->name('logout');
        Route::redirect('/', '/admin/movies');
        Route::resource('movies', AdminMovies::class)->except('show');
        Route::resource('categories', AdminCategories::class)->except('show');
        Route::resource('tags', AdminTags::class)->except('show');
        Route::get('/reports', [AdminReports::class, 'index'])->name('reports.index');
        Route::post('/reports/{movie}/resolve', [AdminReports::class, 'resolve'])->name('reports.resolve');
        Route::get('/users', [AdminUsers::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [AdminUsers::class, 'show'])->name('users.show');
        Route::post('/users/{user}/block', [AdminUsers::class, 'block'])->name('users.block');
        Route::post('/users/{user}/unblock', [AdminUsers::class, 'unblock'])->name('users.unblock');
        Route::post('/users/{user}/reset-link', [AdminUsers::class, 'sendReset'])->middleware('throttle:10,1')->name('users.reset');
        Route::post('/users/{user}/premium', [AdminUsers::class, 'premium'])->name('users.premium');
        Route::delete('/users/{user}', [AdminUsers::class, 'destroy'])->name('users.destroy');
        Route::get('/stats', [AdminStats::class, 'index'])->name('stats');
        Route::get('/settings', [AdminSettings::class, 'edit'])->name('settings');
        Route::put('/settings', [AdminSettings::class, 'update'])->name('settings.update');
        Route::get('/push', [AdminPush::class, 'index'])->name('push.index');
        Route::post('/push', [AdminPush::class, 'store'])->middleware('throttle:10,1')->name('push.store');
        Route::get('/account', [AdminAccount::class, 'edit'])->name('account');
        Route::put('/account/password', [AdminAccount::class, 'updatePassword'])->middleware('throttle:10,1')->name('account.password');
        Route::put('/account/email', [AdminAccount::class, 'updateEmail'])->middleware('throttle:10,1')->name('account.email');
    });
});
