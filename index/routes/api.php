<?php

use App\Http\Controllers\Public\TagController;
use App\Http\Middleware\OpenIdMiddleware;
use Illuminate\Support\Facades\Route;
use Modules\Blog\Presentation\Http\Controllers\AdminPostController;
use Modules\Blog\Presentation\Http\Controllers\PublicPostController;
use Modules\Sso\Presentation\Http\Controllers\SsoTokenController;

// Route::get('/posts', [PublicPostController::class, 'index']);
// Route::get('/posts/{slug}', [PublicPostController::class, 'show']);

// Route::get('/tags', [TagController::class, 'index']);
// Route::get('/tags/{slug}', [TagController::class, 'show']);
// Route::get('/tags/{slug}/posts', [TagController::class, 'getPosts']);

// Route::middleware([OpenIdMiddleware::class])->prefix('admin')->group(function() {
//     Route::apiResource('/posts', AdminPostController::class);
// });

Route::post('/auth/sso/token', [SsoTokenController::class, 'token'])->name('sso.token');
