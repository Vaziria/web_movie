<?php

use App\Http\Controllers\MovieController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MovieController::class, 'index'])->name('movies.index');
Route::get('/category/{category}', [MovieController::class, 'category'])->name('movies.category');
Route::get('/movies/{movie}', [MovieController::class, 'show'])->name('movies.show');
Route::post('/movies/{movie}/rate', [MovieController::class, 'rate'])
    ->middleware('throttle:20,1')
    ->name('movies.rate');

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
