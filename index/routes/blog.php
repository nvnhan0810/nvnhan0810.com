<?php

use Illuminate\Support\Facades\Route;
use Modules\Blog\Presentation\Http\Controllers\AdminPostController;
use Modules\Blog\Presentation\Http\Controllers\PublicPostController;

Route::get('/posts', [PublicPostController::class, 'index'])->name('posts.index');
Route::get('/posts/{slug}', [PublicPostController::class, 'show'])->name('posts.show');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    // Posts admin actions only — /admin hub lives in routes/web.php (admin.index).
    Route::resource('posts', AdminPostController::class)->except(['index', 'show']);
});
