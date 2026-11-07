<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\MovieController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TagController;
use Illuminate\Support\Facades\Route;

// Route names ("api." is dropped) are the operationIds in docs/openapi.json: keep them stable.

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('api.auth.login');

Route::get('/categories', [CategoryController::class, 'index'])->name('api.categories.index');
Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('api.categories.show');
Route::get('/tags', [TagController::class, 'index'])->name('api.tags.index');
Route::get('/tags/{tag}', [TagController::class, 'show'])->name('api.tags.show');
Route::get('/movies', [MovieController::class, 'index'])->name('api.movies.index');
Route::get('/movies/{movie}', [MovieController::class, 'show'])->name('api.movies.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('api.auth.me');
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

    Route::post('/movies', [MovieController::class, 'store'])->name('api.movies.store');
    // POST also updates, because PHP only parses multipart file uploads (thumbnail) on POST.
    // Separate routes (not Route::match) so the API docs list every method.
    Route::patch('/movies/{movie}', [MovieController::class, 'update'])->name('api.movies.update');
    Route::put('/movies/{movie}', [MovieController::class, 'update'])->name('api.movies.update.put');
    Route::post('/movies/{movie}', [MovieController::class, 'update'])->name('api.movies.update.multipart');
    Route::delete('/movies/{movie}', [MovieController::class, 'destroy'])->name('api.movies.destroy');

    Route::post('/categories', [CategoryController::class, 'store'])->name('api.categories.store');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('api.categories.update');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('api.categories.update.put');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('api.categories.destroy');

    Route::post('/tags', [TagController::class, 'store'])->name('api.tags.store');
    Route::patch('/tags/{tag}', [TagController::class, 'update'])->name('api.tags.update');
    Route::put('/tags/{tag}', [TagController::class, 'update'])->name('api.tags.update.put');
    Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('api.tags.destroy');

    Route::get('/reports', [ReportController::class, 'index'])->name('api.reports.index');
    Route::post('/reports/{movie}/resolve', [ReportController::class, 'resolve'])->name('api.reports.resolve');
});
