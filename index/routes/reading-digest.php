<?php

use App\Http\Controllers\Admin\ReadingDigest\ArticleInboxController;
use App\Http\Controllers\Admin\ReadingDigest\SettingsController;
use App\Http\Controllers\Admin\ReadingDigest\SourceController;
use App\Http\Controllers\Admin\ReadingDigest\SubjectController;
use App\Http\Controllers\Admin\ReadingDigest\TaxonomyController;
use App\Http\Controllers\Admin\ReadingDigest\TodayDigestController;
use App\Http\Controllers\Public\PublicNewsController;
use Illuminate\Support\Facades\Route;

Route::get('/news', [PublicNewsController::class, 'index'])->name('news.index');

Route::middleware('auth')->group(function () {
    Route::get('/news/today', [PublicNewsController::class, 'today'])->name('news.today');
    Route::post('/news/vote/{token}', [PublicNewsController::class, 'vote'])
        ->middleware('throttle:60,1')
        ->name('news.vote');
    Route::get('/news/open/{token}', [PublicNewsController::class, 'open'])
        ->name('news.open');

    Route::prefix('admin/reading-digest')->name('admin.reading-digest.')->group(function () {
        Route::redirect('/', '/admin/reading-digest/today')->name('index');

        Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
        Route::get('/subjects/create', [SubjectController::class, 'create'])->name('subjects.create');
        Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
        Route::get('/subjects/{id}/edit', [SubjectController::class, 'edit'])->name('subjects.edit');
        Route::put('/subjects/{id}', [SubjectController::class, 'update'])->name('subjects.update');
        Route::delete('/subjects/{id}', [SubjectController::class, 'destroy'])->name('subjects.destroy');

        Route::get('/sources', [SourceController::class, 'index'])->name('sources.index');
        Route::get('/sources/create', [SourceController::class, 'create'])->name('sources.create');
        Route::post('/sources', [SourceController::class, 'store'])->name('sources.store');
        Route::get('/sources/{id}/edit', [SourceController::class, 'edit'])->name('sources.edit');
        Route::put('/sources/{id}', [SourceController::class, 'update'])->name('sources.update');
        Route::delete('/sources/{id}', [SourceController::class, 'destroy'])->name('sources.destroy');
        Route::post('/sources/{id}/fetch', [SourceController::class, 'fetchNow'])->name('sources.fetch');
        Route::get('/sources/{id}/test', [SourceController::class, 'testFetch'])->name('sources.test');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/reset-learning', [SettingsController::class, 'resetLearning'])->name('settings.reset-learning');
        Route::post('/send-now', [SettingsController::class, 'sendNow'])->name('send-now');
        Route::post('/preview', [SettingsController::class, 'preview'])->name('preview');

        Route::get('/today', [TodayDigestController::class, 'index'])->name('today');
        Route::get('/profile', fn () => redirect()->route('admin.reading-digest.settings.index'))->name('profile.index');
        Route::post('/profile/reset', fn () => redirect()->route('admin.reading-digest.settings.index'))->name('profile.reset');

        Route::get('/taxonomy', [TaxonomyController::class, 'index'])->name('taxonomy.index');
        Route::post('/taxonomy', [TaxonomyController::class, 'store'])->name('taxonomy.store');

        Route::get('/articles', [ArticleInboxController::class, 'index'])->name('articles.index');
        Route::patch('/articles/{id}', [ArticleInboxController::class, 'update'])->name('articles.update');
    });
});
