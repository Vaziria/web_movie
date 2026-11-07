<?php

use App\Http\Controllers\Admin\AuthController as AdminAuth;
use App\Http\Controllers\Admin\CategoryController as AdminCategories;
use App\Http\Controllers\Admin\MovieController as AdminMovies;
use App\Http\Controllers\Admin\ReportController as AdminReports;
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

    Route::middleware('admin')->group(function () {
        Route::post('/logout', [AdminAuth::class, 'logout'])->name('logout');
        Route::redirect('/', '/admin/movies');
        Route::resource('movies', AdminMovies::class)->except('show');
        Route::resource('categories', AdminCategories::class)->except('show');
        Route::get('/reports', [AdminReports::class, 'index'])->name('reports.index');
        Route::post('/reports/{movie}/resolve', [AdminReports::class, 'resolve'])->name('reports.resolve');
    });
});
