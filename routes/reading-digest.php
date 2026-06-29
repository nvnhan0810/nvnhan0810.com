<?php

use App\Domains\ReadingDigest\Presentation\Http\Controllers\ArticleInboxController;
use App\Domains\ReadingDigest\Presentation\Http\Controllers\ArticleRedirectController;
use App\Domains\ReadingDigest\Presentation\Http\Controllers\InteractionController;
use App\Domains\ReadingDigest\Presentation\Http\Controllers\ProfileDashboardController;
use App\Domains\ReadingDigest\Presentation\Http\Controllers\SettingsController;
use App\Domains\ReadingDigest\Presentation\Http\Controllers\SourceController;
use App\Domains\ReadingDigest\Presentation\Http\Controllers\SubjectController;
use App\Domains\ReadingDigest\Presentation\Http\Controllers\TaxonomyController;
use App\Domains\ReadingDigest\Presentation\Http\Controllers\TodayDigestController;
use Illuminate\Support\Facades\Route;

Route::get('/reading-digest/a/{token}', [ArticleRedirectController::class, 'show'])
    ->name('reading-digest.article.redirect');

Route::middleware('auth')->group(function () {
    Route::post('/reading-digest/interactions', [InteractionController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('reading-digest.interactions.store');

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
        Route::post('/send-now', [SettingsController::class, 'sendNow'])->name('send-now');
        Route::post('/preview', [SettingsController::class, 'preview'])->name('preview');

        Route::get('/today', [TodayDigestController::class, 'index'])->name('today');
        Route::get('/profile', [ProfileDashboardController::class, 'index'])->name('profile.index');
        Route::post('/profile/reset', [ProfileDashboardController::class, 'reset'])->name('profile.reset');

        Route::get('/taxonomy', [TaxonomyController::class, 'index'])->name('taxonomy.index');
        Route::post('/taxonomy', [TaxonomyController::class, 'store'])->name('taxonomy.store');

        Route::get('/articles', [ArticleInboxController::class, 'index'])->name('articles.index');
        Route::patch('/articles/{id}', [ArticleInboxController::class, 'update'])->name('articles.update');
    });
});
