<?php

use App\Http\Controllers\Admin\AccountController as AdminAccount;
use App\Http\Controllers\Admin\AuthController as AdminAuth;
use App\Http\Controllers\Admin\CategoryController as AdminCategories;
use App\Http\Controllers\Admin\MovieController as AdminMovies;
use App\Http\Controllers\Admin\PasswordResetController as AdminPasswordReset;
use App\Http\Controllers\Admin\ReportController as AdminReports;
use App\Http\Controllers\Admin\TagController as AdminTags;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

/*
 * Public site in two languages: Indonesian at / (route names "movies.*") and English under /en ("en.movies.*").
 * Build links with lroute(), which picks the current locale.
 */
$public = function () {
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
Route::middleware('locale:id')->group($public);
Route::prefix('en')->name('en.')->middleware('locale:en')->group($public);

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
        Route::get('/account', [AdminAccount::class, 'edit'])->name('account');
        Route::put('/account/password', [AdminAccount::class, 'updatePassword'])->middleware('throttle:10,1')->name('account.password');
        Route::put('/account/email', [AdminAccount::class, 'updateEmail'])->middleware('throttle:10,1')->name('account.email');
    });
});
