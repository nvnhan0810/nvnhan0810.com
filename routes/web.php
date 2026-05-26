<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Admin\PostController as AdminPostController;
use App\Http\Controllers\Admin\TagController as AdminTagController;
use App\Http\Controllers\Admin\SeriesController as AdminSeriesController;
use App\Http\Controllers\Public\PostController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::prefix('auth')->group(function () {
    Route::get('/login', function () {
        abort(403);
    })->name('login');

    Route::get('/google/login', [AuthController::class, 'login'])->name('google.login');
    Route::get('/callback', [AuthController::class, 'callback'])->name('login.callback');
});

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/', function () {
    return Inertia::render('public/HomePage');
})->name('home');

Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
Route::get('/posts/{slug}', [PostController::class, 'show'])->name('posts.show');


Route::get('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {

    Route::get('/', [AdminPostController::class, 'index'])->name('index');

    Route::resource('posts', AdminPostController::class)->except(['index', 'show']);

    Route::resource('tags', AdminTagController::class)->except(['show', 'create', 'store']);

    Route::resource('series', AdminSeriesController::class)->except(['show']);
});
