<?php

use App\Http\Controllers\Admin\PostController as AdminPostController;
use App\Http\Controllers\Public\PostController;
use App\Http\Controllers\Public\TagController;
use App\Http\Controllers\Agent\PostAgentPostsController;
use App\Http\Middleware\OpenIdMiddleware;
use App\Http\Middleware\AgentApiTokenMiddleware;
use Illuminate\Support\Facades\Route;
use App\Http\Requests\Admin\CreatePostRequest;
use App\Http\Requests\Admin\UpdatePostRequest;

// Route::get('/posts', [PostController::class, 'index']);
// Route::get('/posts/{slug}', [PostController::class, 'show']);

// Route::get('/tags', [TagController::class, 'index']);
// Route::get('/tags/{slug}', [TagController::class, 'show']);
// Route::get('/tags/{slug}/posts', [TagController::class, 'getPosts']);

// Route::middleware([OpenIdMiddleware::class])->prefix('admin')->group(function() {
//     Route::apiResource('/posts', AdminPostController::class);
// });

Route::middleware([AgentApiTokenMiddleware::class])->prefix('agent')->group(function () {
    Route::get('/posts', [PostAgentPostsController::class, 'index']);
    Route::get('/posts/{id}', [PostAgentPostsController::class, 'show']);
    Route::post('/posts/draft', [PostAgentPostsController::class, 'createDraft']);
    Route::put('/posts/{id}', [PostAgentPostsController::class, 'update']);
    Route::post('/posts/{id}/publish', [PostAgentPostsController::class, 'publish']);
});

