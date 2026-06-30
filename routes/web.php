<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Admin\PostController as AdminPostController;
use App\Http\Controllers\Admin\TagController as AdminTagController;
use App\Http\Controllers\Admin\SeriesController as AdminSeriesController;
use App\Http\Controllers\Public\AppShowController;
use App\Http\Controllers\Public\AppsController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\OgImageController;
use App\Http\Controllers\Public\PostController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\Response;

Route::prefix('auth')->group(function () {
    Route::get('/login', function () {
        return redirect()->route('google.login');
    })->name('login');

    Route::get('/google/login', [AuthController::class, 'login'])->name('google.login');
    Route::get('/callback', [AuthController::class, 'callback'])->name('login.callback');
});

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/favicon.ico', function () {
    return response()->file(public_path('images/favicon-32x32.png'), [
        'Content-Type' => 'image/png',
        'Cache-Control' => 'public, max-age=604800',
    ]);
});

Route::get('/', HomeController::class)->name('home');

Route::get('/og/home.png', [OgImageController::class, 'home'])->name('og.home');

Route::get('/og/posts/{slug}.png', [OgImageController::class, 'post'])
    ->where('slug', '[A-Za-z0-9\-]+')
    ->name('og.posts.show');

Route::get('/apps', AppsController::class)->name('apps.index');
Route::get('/apps/{slug}', AppShowController::class)
    ->where('slug', '[a-z0-9\-]+')
    ->name('apps.show');

Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
Route::get('/posts/{slug}', [PostController::class, 'show'])->name('posts.show');

Route::get('/robots.txt', function () {
    $content = implode("\n", [
        'User-agent: *',
        'Allow: /',
        'Sitemap: '.url('/sitemap.xml'),
    ]);

    return Response::make($content, 200, [
        'Content-Type' => 'text/plain; charset=UTF-8',
    ]);
});

Route::get('/sitemap.xml', function () {
    $posts = \App\Models\Post::query()
        ->where('is_published', true)
        ->whereDate('published_at', '<=', now())
        ->orderByDesc('updated_at')
        ->get(['slug', 'updated_at']);

    $urls = collect([
        [
            'loc' => url('/'),
            'lastmod' => now()->toAtomString(),
            'changefreq' => 'weekly',
            'priority' => '1.0',
        ],
        [
            'loc' => url('/posts'),
            'lastmod' => now()->toAtomString(),
            'changefreq' => 'daily',
            'priority' => '0.8',
        ],
        [
            'loc' => url('/apps'),
            'lastmod' => now()->toAtomString(),
            'changefreq' => 'monthly',
            'priority' => '0.6',
        ],
        [
            'loc' => url('/apps/foreign-language-course'),
            'lastmod' => now()->toAtomString(),
            'changefreq' => 'monthly',
            'priority' => '0.6',
        ],
        [
            'loc' => url('/apps/db-management-tool'),
            'lastmod' => now()->toAtomString(),
            'changefreq' => 'monthly',
            'priority' => '0.6',
        ],
    ])->merge(
        $posts->map(fn ($post) => [
            'loc' => url('/posts/'.$post->slug),
            'lastmod' => optional($post->updated_at)->toAtomString(),
            'changefreq' => 'weekly',
            'priority' => '0.7',
        ])
    );

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
        .view('sitemap', compact('urls'))->render();

    return Response::make($xml, 200, [
        'Content-Type' => 'application/xml; charset=UTF-8',
    ]);
});


Route::get('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {

    Route::get('/', [AdminPostController::class, 'index'])->name('index');

    Route::resource('posts', AdminPostController::class)->except(['index', 'show']);

    Route::resource('tags', AdminTagController::class)->except(['show', 'create', 'store']);

    Route::resource('series', AdminSeriesController::class)->except(['show']);
});
